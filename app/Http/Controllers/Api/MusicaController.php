<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CancionArtista;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MusicaController extends Controller
{
    private const URL_DEEZER = 'https://api.deezer.com';
    private const TTL_CHARTS  = 1800;  // 30 min
    private const TTL_GENEROS = 86400; // 24 h
    private const TTL_BUSQUEDA = 300;  // 5 min

    // =========================================================================
    // Charts: canciones, artistas y álbumes más populares
    // =========================================================================

    public function charts()
    {
        // Cache manual: evitar cachear respuestas vacías de Deezer (rate-limit silencioso)
        $cacheKey = 'musica_charts_v6';
        $datos = Cache::get($cacheKey);
        if (!$datos || empty($datos['chart']['tracks']['data'] ?? null)) {
            $datos = (function () {
                // Primera ronda: chart general y top tracks en paralelo
                [$rChart, $rTracks] = Http::pool(fn ($pool) => [
                    $pool->timeout(10)->get(self::URL_DEEZER . '/chart/0'),
                    $pool->timeout(10)->get(self::URL_DEEZER . '/chart/0/tracks', ['limit' => 20]),
                ]);

                if ($rChart->failed()) return null;

                $chartJson = $rChart->json();
                $artistIds = collect($chartJson['artists']['data'] ?? [])->take(8)->pluck('id')->all();

                // Segunda ronda: detalle de cada artista
                $detalles = Http::pool(fn ($pool) => array_map(
                    fn($id) => $pool->timeout(10)->get(self::URL_DEEZER . "/artist/{$id}"),
                    $artistIds
                ));

                $artistasDetalle = collect($detalles)
                    ->filter(fn($r) => $r->successful())
                    ->map(fn($r) => $r->json())
                    ->values()
                    ->all();

                return [
                    'chart'             => $chartJson,
                    'tracks'            => $rTracks->successful() ? $rTracks->json() : null,
                    'artistas_detalle'  => $artistasDetalle,
                    'primer_artista_id' => $artistIds[0] ?? null,
                ];
            })();

            // Solo cachear si los datos son válidos y no vacíos
            if ($datos && !empty($datos['chart']['tracks']['data'] ?? null)) {
                Cache::put($cacheKey, $datos, self::TTL_CHARTS);
            }
        }

        if (!$datos) {
            return response()->json(['error' => 'No se pudo obtener el contenido.'], 503);
        }

        // Artista #1 de la semana — siempre el primero del chart Deezer (fuera del cache para usar picture_xl)
        $primerArtistaId    = $datos['primer_artista_id'] ?? null;
        $artistaSemanaDatos = $primerArtistaId
            ? collect($datos['artistas_detalle'])->firstWhere('id', $primerArtistaId)
            : ($datos['artistas_detalle'][0] ?? null);
        if (!$artistaSemanaDatos && !empty($datos['artistas_detalle'])) {
            $artistaSemanaDatos = $datos['artistas_detalle'][0];
        }
        $artistaSemana = $artistaSemanaDatos ? [
            'id'          => $artistaSemanaDatos['id'],
            'nombre'      => $artistaSemanaDatos['name'],
            'imagen'      => $artistaSemanaDatos['picture_xl'] ?? $artistaSemanaDatos['picture_big'] ?? $artistaSemanaDatos['picture_medium'] ?? null,
            'descripcion' => number_format($artistaSemanaDatos['nb_fan'] ?? 0, 0, ',', '.') . ' oyentes',
            'tipo'        => 'artista',
        ] : null;

        $trackData = $datos['tracks']['data'] ?? $datos['chart']['tracks']['data'] ?? [];

        $canciones = collect($trackData)->take(20)->map(fn($t) => [
            'id'          => $t['id'],
            'nombre'      => $t['title'],
            'artista'     => $t['artist']['name'],
            'artista_id'  => $t['artist']['id'],
            'imagen'      => $t['album']['cover_medium'] ?? null,
            'descripcion' => $t['artist']['name'],
            'duracion'    => $this->segundosAMinutos($t['duration'] ?? 0),
            'preview'     => $t['preview'] ?? null,
            'tipo'        => 'cancion',
        ])->values();

        $albumes = collect($datos['chart']['albums']['data'] ?? [])->take(8)->map(fn($a) => [
            'id'      => $a['id'],
            'nombre'  => $a['title'],
            'artista' => $a['artist']['name'],
            'imagen'  => $a['cover_medium'] ?? null,
            'tipo'    => 'album',
        ])->values();

        // Artistas con nb_fan real obtenido del endpoint /artist/{id}
        $artistas = collect($datos['artistas_detalle'] ?? [])->map(fn($a) => [
            'id'          => $a['id'],
            'nombre'      => $a['name'],
            'imagen'      => $a['picture_medium'] ?? null,
            'descripcion' => number_format($a['nb_fan'] ?? 0, 0, ',', '.') . ' oyentes',
            'tipo'        => 'artista',
        ])->values();

        return response()->json(compact('canciones', 'albumes', 'artistas', 'artistaSemana'));
    }

    // =========================================================================
    // Detalle de un artista: info + top canciones + álbumes
    // =========================================================================

    public function artista($id)
    {
        $id = (int) $id;

        $datos = Cache::remember("artista_{$id}_v6", self::TTL_CHARTS, function () use ($id) {
            [$rArtista, $rCanciones, $rAlbumes, $rRelacionados] = Http::pool(fn ($pool) => [
                $pool->timeout(10)->get(self::URL_DEEZER . "/artist/{$id}"),
                $pool->timeout(10)->get(self::URL_DEEZER . "/artist/{$id}/top", ['limit' => 10]),
                $pool->timeout(10)->get(self::URL_DEEZER . "/artist/{$id}/albums", ['limit' => 12]),
                $pool->timeout(10)->get(self::URL_DEEZER . "/artist/{$id}/related", ['limit' => 6]),
            ]);

            if ($rArtista->failed()) return null;

            $nombreArtista = $rArtista->json()['name'] ?? '';
            $tituloWiki    = rawurlencode(str_replace(' ', '_', $nombreArtista));
            $headers       = ['User-Agent' => 'Sonalya/1.0 (academic project)'];

            // Dos peticiones Wikipedia en paralelo:
            // 1) REST summary → descripción corta
            // 2) MediaWiki API exintro → texto completo de la introducción
            [$rWikiSummary, $rWikiExtract] = Http::pool(fn ($pool) => [
                $pool->timeout(8)->withHeaders($headers)
                    ->get("https://es.wikipedia.org/api/rest_v1/page/summary/{$tituloWiki}"),
                $pool->timeout(10)->withHeaders($headers)
                    ->get('https://es.wikipedia.org/w/api.php', [
                        'action'      => 'query',
                        'format'      => 'json',
                        'prop'        => 'extracts',
                        'exintro'     => true,
                        'explaintext' => true,
                        'redirects'   => 1,
                        'titles'      => $nombreArtista,
                    ]),
            ]);

            $wiki     = null;
            $wikiDesc = null;

            if ($rWikiSummary->successful()) {
                $s = $rWikiSummary->json();
                if (($s['type'] ?? '') !== 'disambiguation') {
                    $wikiDesc = $s['description'] ?? null;
                }
            }

            if ($rWikiExtract->successful()) {
                $pages = $rWikiExtract->json()['query']['pages'] ?? [];
                $page  = array_values($pages)[0] ?? null;
                if ($page && !isset($page['missing']) && !empty($page['extract'])) {
                    $wiki = trim($page['extract']);
                }
            }

            // Fallback: si la MediaWiki no devolvió texto, usar el extract del summary
            if (!$wiki && $rWikiSummary->successful()) {
                $s = $rWikiSummary->json();
                if (!empty($s['extract']) && ($s['type'] ?? '') !== 'disambiguation') {
                    $wiki = trim($s['extract']);
                }
            }

            return [
                'artista'      => $rArtista->json(),
                'canciones'    => $rCanciones->json(),
                'albumes'      => $rAlbumes->json(),
                'relacionados' => $rRelacionados->successful() ? $rRelacionados->json() : null,
                'bio'          => $wiki,
                'bioDesc'      => $wikiDesc,
            ];
        });

        if (!$datos) {
            return response()->json(['error' => 'No se pudo obtener el artista.'], 503);
        }

        $info = $datos['artista'];

        $artista = [
            'id'              => $info['id'],
            'nombre'          => $info['name'],
            'imagen'          => $info['picture_xl']  ?? $info['picture_big'] ?? $info['picture_medium'] ?? null,
            'imagenMedium'    => $info['picture_medium'] ?? null,
            'fans'            => number_format($info['nb_fan'] ?? 0, 0, ',', '.'),
            'cantidadAlbumes' => $info['nb_album'] ?? 0,
        ];

        $canciones = collect($datos['canciones']['data'] ?? [])->map(fn($t) => [
            'id'        => $t['id'],
            'titulo'    => $t['title'],
            'artista'   => $t['artist']['name'],
            'artista_id' => $t['artist']['id'],
            'album'     => $t['album']['title'],
            'imagen'    => $t['album']['cover_medium'] ?? null,
            'duracion'  => $this->segundosAMinutos($t['duration'] ?? 0),
            'preview'   => $t['preview'] ?? null,
        ])->values();

        $albumes = collect($datos['albumes']['data'] ?? [])->map(fn($a) => [
            'id'      => $a['id'],
            'nombre'  => $a['title'],
            'imagen'  => $a['cover_medium'] ?? null,
            'artista' => $info['name'],
            'tipo'    => 'album',
        ])->values();

        $relacionados = collect($datos['relacionados']['data'] ?? [])->take(6)->map(fn($a) => [
            'id'          => $a['id'],
            'nombre'      => $a['name'],
            'imagen'      => $a['picture_medium'] ?? null,
            'descripcion' => number_format($a['nb_fan'] ?? 0, 0, ',', '.') . ' oyentes',
            'tipo'        => 'artista',
        ])->values();

        $bio     = $datos['bio']     ?? null;
        $bioDesc = $datos['bioDesc'] ?? null;

        return response()->json(compact('artista', 'canciones', 'albumes', 'bio', 'bioDesc', 'relacionados'));
    }

    // =========================================================================
    // Artistas emergentes: géneros alternativos con pocos oyentes
    // =========================================================================

    public function emergentes()
    {
        $artistas = Cache::remember('artistas_emergentes_v5', self::TTL_CHARTS, function () {
            // 6 géneros diversos en paralelo para garantizar variedad real
            [$rBlues, $rSoul, $rReggaeton, $rBossa, $rFlamenco, $rAfrobeat] = Http::pool(fn ($pool) => [
                $pool->timeout(10)->get(self::URL_DEEZER . '/search/artist', ['q' => 'blues',     'limit' => 25]),
                $pool->timeout(10)->get(self::URL_DEEZER . '/search/artist', ['q' => 'soul',      'limit' => 25]),
                $pool->timeout(10)->get(self::URL_DEEZER . '/search/artist', ['q' => 'reggaeton', 'limit' => 25]),
                $pool->timeout(10)->get(self::URL_DEEZER . '/search/artist', ['q' => 'bossa nova','limit' => 25]),
                $pool->timeout(10)->get(self::URL_DEEZER . '/search/artist', ['q' => 'flamenco',  'limit' => 25]),
                $pool->timeout(10)->get(self::URL_DEEZER . '/search/artist', ['q' => 'afrobeat',  'limit' => 25]),
            ]);

            // Hasta 3 artistas de cada género, mezclados en un único array plano
            $porGenero = [
                $rBlues->successful()     ? array_slice($rBlues->json()['data']     ?? [], 0, 3) : [],
                $rSoul->successful()      ? array_slice($rSoul->json()['data']      ?? [], 0, 3) : [],
                $rReggaeton->successful() ? array_slice($rReggaeton->json()['data'] ?? [], 0, 3) : [],
                $rBossa->successful()     ? array_slice($rBossa->json()['data']     ?? [], 0, 3) : [],
                $rFlamenco->successful()  ? array_slice($rFlamenco->json()['data']  ?? [], 0, 3) : [],
                $rAfrobeat->successful()  ? array_slice($rAfrobeat->json()['data']  ?? [], 0, 3) : [],
            ];

            $todos = collect(array_merge(...$porGenero))->unique('id');

            $candidatos = $todos
                ->filter(fn($a) => isset($a['nb_fan']) && $a['nb_fan'] > 1000 && $a['nb_fan'] < 800000)
                ->shuffle()
                ->take(8);

            // Fallback: relax fan filter si no hay suficientes
            if ($candidatos->count() < 4) {
                $candidatos = $todos
                    ->filter(fn($a) => ($a['nb_fan'] ?? 0) > 0)
                    ->shuffle()
                    ->take(8);
            }

            return $candidatos->map(fn($a) => [
                'id'          => $a['id'],
                'nombre'      => $a['name'],
                'imagen'      => $a['picture_medium'] ?? null,
                'descripcion' => number_format($a['nb_fan'] ?? 0, 0, ',', '.') . ' oyentes',
                'tipo'        => 'artista',
            ])->values();
        });

        return response()->json(compact('artistas'));
    }

    // =========================================================================
    // Detalle de un álbum: info + lista de canciones
    // =========================================================================

    public function album($id)
    {
        $id = (int) $id;

        $datos = Cache::remember("album_{$id}", self::TTL_CHARTS, function () use ($id) {
            [$rAlbum, $rCanciones] = Http::pool(fn ($pool) => [
                $pool->timeout(10)->get(self::URL_DEEZER . "/album/{$id}"),
                $pool->timeout(10)->get(self::URL_DEEZER . "/album/{$id}/tracks"),
            ]);

            if ($rAlbum->failed()) return null;

            return [
                'album'     => $rAlbum->json(),
                'canciones' => $rCanciones->json(),
            ];
        });

        if (!$datos) {
            return response()->json(['error' => 'No se pudo obtener el álbum.'], 503);
        }

        $info = $datos['album'];

        $album = [
            'id'      => $info['id'],
            'nombre'  => $info['title'],
            'artista' => $info['artist']['name'] ?? '',
            'imagen'  => $info['cover_xl'] ?? $info['cover_big'] ?? $info['cover_medium'] ?? null,
            'fecha'   => substr($info['release_date'] ?? '', 0, 4),
            'genero'  => $info['genres']['data'][0]['name'] ?? null,
            'fans'    => number_format($info['fans'] ?? 0, 0, ',', '.'),
        ];

        $canciones = collect($datos['canciones']['data'] ?? [])->map(fn($t) => [
            'id'        => $t['id'],
            'titulo'    => $t['title'],
            'artista'   => $info['artist']['name'] ?? '',
            'artista_id' => $info['artist']['id'] ?? null,
            'album'     => $info['title'],
            'imagen'    => $info['cover_medium'] ?? null,
            'duracion'  => $this->segundosAMinutos($t['duration'] ?? 0),
            'preview'   => $t['preview'] ?? null,
        ])->values();

        return response()->json(compact('album', 'canciones'));
    }

    // =========================================================================
    // Contenido de un género: populares + emergentes + canciones
    // =========================================================================

    public function porGenero($id, Request $request)
    {
        $id     = (int) $id;
        $nombre = trim($request->query('nombre', 'music'));
        if ($nombre === '') $nombre = 'music';

        $datos = Cache::remember("genero_{$id}_v15", self::TTL_CHARTS, function () use ($id, $nombre) {

            // ── Paso 1: artistas del género + chart del género (paralelo) ─────
            [$rGenero, $rChart] = Http::pool(fn ($pool) => [
                $pool->timeout(12)->get(self::URL_DEEZER . "/genre/{$id}/artists"),
                $pool->timeout(12)->get(self::URL_DEEZER . "/chart/{$id}"),
            ]);

            $chartData  = $rChart->successful() ? $rChart->json() : null;
            $tieneChart = !empty($chartData['tracks']['data'] ?? []);

            // /genre/{id}/artists y /chart/{id}/artists devuelven el mismo listado regional
            // para todos los géneros. La verificación por album.genre_id es la que filtra.
            $idsGenero = collect($rGenero->successful() ? ($rGenero->json()['data'] ?? []) : [])
                            ->pluck('id')->map(fn($v) => (int)$v)->take(50)->all();

            // Los artistas de los tracks del chart SÍ son específicos del género
            $idsChartTrack = collect($chartData['tracks']['data'] ?? [])
                                ->pluck('artist.id')->unique()->map(fn($v) => (int)$v)->values()->all();

            // Artistas del chart no presentes en la lista del género → necesitan detalle extra
            $idsExtras = collect($idsChartTrack)->diff($idsGenero)->values()->all();

            $detalleMap    = [];
            $albumsDelGenero = [];

            $n = count($idsGenero);
            $m = count($idsExtras);

            if ($n > 0 || $m > 0) {
                // ── Paso 2: un único pool paralelo ───────────────────────────
                // Índices 0..n-1:    detalles de artistas del género (nb_fan, picture)
                // Índices n..2n-1:   álbumes de artistas del género (verificación)
                // Índices 2n..2n+m-1: detalles de artistas extra del chart
                $allResp = Http::pool(function ($pool) use ($idsGenero, $idsExtras) {
                    $reqs = [];
                    foreach ($idsGenero as $aid) {
                        $reqs[] = $pool->timeout(8)->get(self::URL_DEEZER . "/artist/{$aid}");
                    }
                    foreach ($idsGenero as $aid) {
                        $reqs[] = $pool->timeout(6)->get(self::URL_DEEZER . "/artist/{$aid}/albums", ['limit' => 10]);
                    }
                    foreach ($idsExtras as $aid) {
                        $reqs[] = $pool->timeout(8)->get(self::URL_DEEZER . "/artist/{$aid}");
                    }
                    return $reqs;
                });

                // Procesar detalles de artistas del género (bloque 1)
                for ($i = 0; $i < $n; $i++) {
                    $r = $allResp[$i];
                    if ($r->successful()) {
                        $a = $r->json();
                        if (isset($a['id'])) {
                            $detalleMap[(int) $a['id']] = array_merge($a, ['_verified' => false, '_chart' => false]);
                        }
                    }
                }

                // Géneros hermanos: Deezer usa genre_id inconsistente entre artistas y álbumes
                $familias = [
                    152 => [152, 85, 464],
                    85  => [85, 152, 464],
                    464 => [464, 152, 85],
                    116 => [116, 122, 197],
                    122 => [122, 116, 197],
                    197 => [197, 122, 116],
                    113 => [113, 106],
                    106 => [106, 113],
                    132 => [132, 165],
                    165 => [165, 132],
                    129 => [129, 169, 153],
                    169 => [169, 129, 153],
                    153 => [153, 169, 129],
                    144 => [144],
                    98  => [98],
                ];
                $familia = $familias[$id] ?? [$id];

                // Verificar género por álbumes (bloque 2): ≥60% de álbumes en la familia
                for ($i = 0; $i < $n; $i++) {
                    $aid = $idsGenero[$i];
                    $r   = $allResp[$n + $i];
                    if (!isset($detalleMap[$aid]) || !$r->successful()) continue;

                    $albs  = array_filter($r->json()['data'] ?? [], fn($a) => ($a['genre_id'] ?? 0) > 0);
                    $total = count($albs);
                    if ($total === 0) continue;

                    $albumsFamilia = array_values(array_filter($albs, fn($a) => in_array((int)$a['genre_id'], $familia)));
                    $coinciden     = count($albumsFamilia);

                    if ($coinciden > 0 && ($coinciden / $total) >= 0.60) {
                        $detalleMap[$aid]['_verified'] = true;
                        foreach ($albumsFamilia as $alb) {
                            if ((int)$alb['genre_id'] === $id) {
                                $albumsDelGenero[] = [
                                    'id'      => $alb['id'],
                                    'nombre'  => $alb['title'],
                                    'artista' => $detalleMap[$aid]['name'] ?? '',
                                    'imagen'  => $alb['cover_medium'] ?? null,
                                    'tipo'    => 'album',
                                ];
                            }
                        }
                    }
                }

                // Artistas extra del chart (bloque 3): pre-verificados por aparecer en el chart
                for ($i = 0; $i < $m; $i++) {
                    $r = $allResp[2 * $n + $i];
                    if ($r->successful()) {
                        $a = $r->json();
                        if (isset($a['id'])) {
                            $detalleMap[(int) $a['id']] = array_merge($a, ['_verified' => false, '_chart' => true]);
                        }
                    }
                }

                // Marcar _chart en artistas del género que también están en los tracks del chart
                foreach ($idsChartTrack as $aid) {
                    if (isset($detalleMap[$aid])) $detalleMap[$aid]['_chart'] = true;
                }
            }

            // ── Fallback canciones cuando el chart no trae tracks ──────────────
            $cancionesFallback = null;
            if (!$tieneChart) {
                // Usar top tracks de artistas verificados (más fiable que búsqueda por texto)
                $topVerificados = collect($detalleMap)
                    ->filter(fn($a) => $a['_verified'] ?? false)
                    ->sortByDesc('nb_fan')->take(4)->keys()->values()->all();

                if (!empty($topVerificados)) {
                    $respTop = Http::pool(fn($pool) => array_map(
                        fn($aid) => $pool->timeout(6)->get(self::URL_DEEZER . "/artist/{$aid}/top", ['limit' => 8]),
                        $topVerificados
                    ));
                    $tracks = [];
                    foreach ($respTop as $r) {
                        if ($r->successful()) $tracks = array_merge($tracks, $r->json()['data'] ?? []);
                    }
                    if (!empty($tracks)) $cancionesFallback = ['data' => $tracks];
                }

                if (!$cancionesFallback) {
                    $rCan = Http::timeout(10)->get(self::URL_DEEZER . '/search', ['q' => $nombre, 'limit' => 30]);
                    $cancionesFallback = $rCan->successful() ? $rCan->json() : null;
                }
            }

            if (empty($detalleMap) && !$chartData && !$cancionesFallback) return null;

            return compact('chartData', 'idsChartTrack', 'detalleMap', 'albumsDelGenero', 'cancionesFallback');
        });

        // ── Extraer con defaults seguros ─────────────────────────────────────
        $chartData         = $datos['chartData']         ?? null;
        $idsChartTrack     = $datos['idsChartTrack']     ?? [];
        $detalleMap        = $datos['detalleMap']        ?? [];
        $albumsDelGenero   = $datos['albumsDelGenero']   ?? [];
        $cancionesFallback = $datos['cancionesFallback'] ?? null;

        $formatArtista = fn($a) => [
            'id'          => $a['id'],
            'nombre'      => $a['name'],
            'imagen'      => $a['picture_medium'] ?? null,
            'descripcion' => number_format($a['nb_fan'] ?? 0, 0, ',', '.') . ' oyentes',
            'nb_fan'      => (int) ($a['nb_fan'] ?? 0),
            'tipo'        => 'artista',
        ];

        // ── Enriquecer detalleMap con artistas del chart directo ─────────────
        // /chart/{id} → artists.data ya incluye nb_fan; los marcamos como chart_direct
        foreach (($chartData['artists']['data'] ?? []) as $a) {
            $aid = (int) $a['id'];
            if (!isset($detalleMap[$aid])) {
                $detalleMap[$aid] = array_merge($a, ['_verified' => false, '_chart' => false]);
            }
            $detalleMap[$aid]['_chart_direct'] = true;
        }

        // Tres grupos con diferente fiabilidad de género:
        // 1. Verificados: ≥60% de sus álbumes tienen el genre_id correcto → más precisos
        $verificados = collect($detalleMap)
            ->filter(fn($a) => $a['_verified'] ?? false)->values();

        // 2. Chart-directo: Deezer los lista en el chart del género → fiables pero pueden incluir crossover
        $directos = collect($detalleMap)
            ->filter(fn($a) => $a['_chart_direct'] ?? false)->values();

        // 3. Chart-track: su canción apareció en el chart del género → indicador moderado
        $deTracks = collect($detalleMap)
            ->filter(fn($a) => $a['_chart'] ?? false)->values();

        // ── POPULARES: unión verificados + chart-directo + chart-track ────────
        // Combinamos las tres fuentes fiables y ordenamos por popularidad real (nb_fan).
        // Usar unión en lugar de match() evita descartar fuentes válidas.
        $poolPopulares = $verificados
            ->merge($directos)
            ->merge($deTracks)
            ->unique('id')
            ->values();

        // Si el pool combinado sigue siendo pequeño, añadir todos los del mapa
        if ($poolPopulares->count() < 4) {
            $poolPopulares = collect($detalleMap)->values();
        }

        $popularesCol = $poolPopulares->sortByDesc('nb_fan')->take(10)->values();
        $idsPopulares = $popularesCol->pluck('id')->map(fn($v) => (int)$v)->all();
        $populares    = $popularesCol->map($formatArtista)->values();

        // ── NUEVOS TALENTOS ───────────────────────────────────────────────────
        // Rango objetivo: 1 000 – 100 000 oyentes (artistas emergentes reales).
        // Candidatos con cascada de pools: verificados → +directos → todos del mapa.
        $todosCandidatos = collect($detalleMap)->values()
            ->filter(fn($a) => !in_array((int)$a['id'], $idsPopulares));

        $candidatosVerif = $verificados->merge($directos)->unique('id')->values()
            ->filter(fn($a) => !in_array((int)$a['id'], $idsPopulares));

        // Intento 1: verificados+directos, rango 1 000 – 100 000
        $emergentes = $candidatosVerif
            ->filter(fn($a) => ($a['nb_fan'] ?? 0) >= 1_000)
            ->filter(fn($a) => ($a['nb_fan'] ?? 0) <= 100_000)
            ->sortBy('nb_fan')->values()->take(10)
            ->map($formatArtista)->values();

        // Intento 2: todos del mapa, rango 1 000 – 100 000
        if ($emergentes->count() < 3) {
            $emergentes = $todosCandidatos
                ->filter(fn($a) => ($a['nb_fan'] ?? 0) >= 1_000)
                ->filter(fn($a) => ($a['nb_fan'] ?? 0) <= 100_000)
                ->sortBy('nb_fan')->values()->take(10)
                ->map($formatArtista)->values();
        }

        // Intento 3: todos del mapa, rango 1 000 – 500 000
        if ($emergentes->count() < 3) {
            $emergentes = $todosCandidatos
                ->filter(fn($a) => ($a['nb_fan'] ?? 0) >= 1_000)
                ->filter(fn($a) => ($a['nb_fan'] ?? 0) <= 500_000)
                ->sortBy('nb_fan')->values()->take(10)
                ->map($formatArtista)->values();
        }

        // Intento 4: todos del mapa, solo mínimo 1 000 oyentes, sin límite superior
        if ($emergentes->count() < 3) {
            $emergentes = $todosCandidatos
                ->filter(fn($a) => ($a['nb_fan'] ?? 0) >= 1_000)
                ->sortBy('nb_fan')->values()->take(10)
                ->map($formatArtista)->values();
        }

        // ── Canciones del chart del género ────────────────────────────────────
        $trackData = $chartData['tracks']['data'] ?? $cancionesFallback['data'] ?? [];
        $canciones = collect($trackData)->take(25)->map(fn($t) => [
            'id'         => $t['id'],
            'titulo'     => $t['title'],
            'artista'    => $t['artist']['name'] ?? '',
            'artista_id' => $t['artist']['id']   ?? null,
            'album'      => $t['album']['title']  ?? '',
            'imagen'     => $t['album']['cover_medium'] ?? null,
            'duracion'   => $this->segundosAMinutos($t['duration'] ?? 0),
            'preview'    => $t['preview'] ?? null,
        ])->values();

        // ── Álbumes: primero los verificados del género, luego del chart ──────
        $albumes = collect($albumsDelGenero)->unique('id')->take(16)->values();
        if ($albumes->isEmpty()) {
            $albumes = collect($chartData['albums']['data'] ?? [])->take(16)->map(fn($a) => [
                'id'      => $a['id'],
                'nombre'  => $a['title'],
                'artista' => $a['artist']['name'] ?? '',
                'imagen'  => $a['cover_medium'] ?? null,
                'tipo'    => 'album',
            ])->values();
        }

        // ── Artistas y canciones de la plataforma Sonalya ────────────────────
        $artistasPlataforma = User::where('tipo', 'artista')
            ->whereHas('artistaPerfil', fn ($q) => $q->where('genero', 'like', "%{$nombre}%"))
            ->withCount('seguidores')->limit(4)->get()
            ->map(fn ($u) => $this->formatearArtistaPlataforma($u));

        $cancionesPlataforma = CancionArtista::where('activa', true)
            ->where('genero', 'like', "%{$nombre}%")
            ->with('artista', 'album')->limit(5)->get()
            ->map(fn ($c) => $c->toFormatoReproductor());

        $emergentes = collect($artistasPlataforma)->merge($emergentes)->values();
        $canciones  = collect($cancionesPlataforma)->merge($canciones)->values();

        return response()->json(compact('populares', 'emergentes', 'canciones', 'albumes'));
    }

    // =========================================================================
    // Géneros musicales de Deezer
    // =========================================================================

    public function generos()
    {
        $datos = Cache::remember('musica_generos', self::TTL_GENEROS, function () {
            $respuesta = Http::timeout(10)->get(self::URL_DEEZER . '/genre');
            if ($respuesta->failed()) {
                return null;
            }
            return $respuesta->json();
        });

        if (!$datos) {
            return response()->json(['error' => 'No se pudo obtener los géneros.'], 503);
        }

        $mapaEstilos = $this->mapaEstilosGenero();

        $generos = collect($datos['data'] ?? [])
            ->filter(fn($g) => $g['id'] !== 0)
            ->take(12)
            ->map(function ($g) use ($mapaEstilos) {
                $estilo = $mapaEstilos[$g['name']] ?? $this->estiloGenerico($g['id']);
                return [
                    'id'     => $g['id'],
                    'nombre' => $g['name'],
                    'imagen' => $g['picture_medium'] ?? null,
                    'color'  => $estilo['color'],
                    'icono'  => $estilo['icono'],
                ];
            })
            ->values();

        return response()->json(compact('generos'));
    }

    // =========================================================================
    // Búsqueda de canciones en Deezer
    // =========================================================================

    public function buscar(Request $request)
    {
        $query = trim($request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['canciones' => [], 'artistas' => []]);
        }

        $claveCache = 'busqueda_v2_' . md5($query);

        $datos = Cache::remember($claveCache, self::TTL_BUSQUEDA, function () use ($query) {
            [$rCanciones, $rArtistas] = Http::pool(fn ($pool) => [
                $pool->timeout(10)->get(self::URL_DEEZER . '/search', ['q' => $query, 'limit' => 20]),
                $pool->timeout(10)->get(self::URL_DEEZER . '/search/artist', ['q' => $query, 'limit' => 8]),
            ]);

            return [
                'canciones' => $rCanciones->successful() ? $rCanciones->json() : null,
                'artistas'  => $rArtistas->successful()  ? $rArtistas->json()  : null,
            ];
        });

        $canciones = collect($datos['canciones']['data'] ?? [])->map(fn($t) => [
            'id'        => $t['id'],
            'titulo'    => $t['title'],
            'artista'   => $t['artist']['name'],
            'artista_id' => $t['artist']['id'],
            'album'     => $t['album']['title'],
            'imagen'    => $t['album']['cover_medium'] ?? null,
            'duracion'  => $this->segundosAMinutos($t['duration'] ?? 0),
            'preview'   => $t['preview'] ?? null,
        ])->values();

        $artistas = collect($datos['artistas']['data'] ?? [])->map(fn($a) => [
            'id'          => $a['id'],
            'nombre'      => $a['name'],
            'imagen'      => $a['picture_medium'] ?? null,
            'descripcion' => number_format($a['nb_fan'] ?? 0, 0, ',', '.') . ' oyentes',
            'tipo'        => 'artista',
        ])->values();

        return response()->json(compact('canciones', 'artistas'));
    }

    // =========================================================================
    // Utilidades privadas
    // =========================================================================

    private function segundosAMinutos(int $segundos): string
    {
        return sprintf('%d:%02d', intdiv($segundos, 60), $segundos % 60);
    }

    private function mapaEstilosGenero(): array
    {
        return [
            'Pop'          => ['color' => 'linear-gradient(135deg, #ff6b6b, #ff5252)',   'icono' => 'fa-music'],
            'Rock'         => ['color' => 'linear-gradient(135deg, #4ecdc4, #44a08d)',   'icono' => 'fa-guitar'],
            'Rap/Hip Hop'  => ['color' => 'linear-gradient(135deg, #ffd93d, #f39c12)',   'icono' => 'fa-microphone-alt'],
            'Hip Hop'      => ['color' => 'linear-gradient(135deg, #ffd93d, #f39c12)',   'icono' => 'fa-microphone-alt'],
            'Electronic'   => ['color' => 'linear-gradient(135deg, #a8c0ff, #3f5efb)',   'icono' => 'fa-bolt'],
            'Dance'        => ['color' => 'linear-gradient(135deg, #fc6c85, #d946c4)',   'icono' => 'fa-fire'],
            'Latin'        => ['color' => 'linear-gradient(135deg, #f7971e, #ffd200)',   'icono' => 'fa-fire'],
            'Jazz'         => ['color' => 'linear-gradient(135deg, #667eea, #764ba2)',   'icono' => 'fa-music'],
            'Classical'    => ['color' => 'linear-gradient(135deg, #ffa751, #ff6b95)',   'icono' => 'fa-music'],
            'R&B'          => ['color' => 'linear-gradient(135deg, #a8e6cf, #3ecc71)',   'icono' => 'fa-headphones'],
            'Reggaeton'    => ['color' => 'linear-gradient(135deg, #ff9a9e, #fad0c4)',   'icono' => 'fa-fire'],
            'K-Pop'        => ['color' => 'linear-gradient(135deg, #f093fb, #f5576c)',   'icono' => 'fa-star'],
            'Indie'        => ['color' => 'linear-gradient(135deg, #a8e6cf, #3ecc71)',   'icono' => 'fa-leaf'],
            'Alternative'  => ['color' => 'linear-gradient(135deg, #4facfe, #00f2fe)',   'icono' => 'fa-guitar'],
            'Metal'        => ['color' => 'linear-gradient(135deg, #434343, #000000)',   'icono' => 'fa-guitar'],
            'Folk'         => ['color' => 'linear-gradient(135deg, #d4a574, #a0714f)',   'icono' => 'fa-leaf'],
            'Country'      => ['color' => 'linear-gradient(135deg, #c79132, #8b5e0a)',   'icono' => 'fa-music'],
            'Films/Games'  => ['color' => 'linear-gradient(135deg, #764ba2, #667eea)',   'icono' => 'fa-film'],
            'Soul'         => ['color' => 'linear-gradient(135deg, #f093fb, #f5576c)',   'icono' => 'fa-heart'],
            'Blues'        => ['color' => 'linear-gradient(135deg, #2980b9, #6dd5fa)',   'icono' => 'fa-music'],
            'Reggae'       => ['color' => 'linear-gradient(135deg, #56ab2f, #a8e063)',   'icono' => 'fa-fire'],
            'Podcasts'     => ['color' => 'linear-gradient(135deg, #8e44ad, #9b59b6)',   'icono' => 'fa-podcast'],
        ];
    }

    private function estiloGenerico(int $id): array
    {
        $paleta = [
            'linear-gradient(135deg, #ff6b6b, #ff5252)',
            'linear-gradient(135deg, #4ecdc4, #44a08d)',
            'linear-gradient(135deg, #a8c0ff, #3f5efb)',
            'linear-gradient(135deg, #ffd93d, #f39c12)',
            'linear-gradient(135deg, #667eea, #764ba2)',
            'linear-gradient(135deg, #fc6c85, #d946c4)',
        ];

        return [
            'color' => $paleta[$id % count($paleta)],
            'icono' => 'fa-music',
        ];
    }

    private function formatearArtistaPlataforma(User $u): array
    {
        return [
            'id'          => $u->id,
            'nombre'      => $u->nombre_artista ?? $u->nombre,
            'imagen'      => $u->url_avatar,
            'descripcion' => ($u->seguidores_count ?? 0) . ' seguidores · Sonalya',
            'tipo'        => 'artista_plataforma',
        ];
    }
}
