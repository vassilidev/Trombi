<?php

namespace App\Support;

/**
 * PCA « maison » pour projeter des embeddings (1536D) en 2D, sans dépendance.
 *
 * Astuce : avec peu d'échantillons (n) et beaucoup de dimensions (d), on
 * diagonalise la matrice de Gram centrée n×n (petite) plutôt que la covariance
 * d×d (énorme). Les coordonnées 2D des points sont alors u_k · sqrt(λ_k).
 */
class VectorProjection
{
    /**
     * @param  list<list<float>>  $vectors  n vecteurs de dimension d.
     * @return array{points: list<array{x: float, y: float}>, variance: array{0: float, 1: float}}
     */
    public static function pca(array $vectors): array
    {
        $n = count($vectors);

        if ($n < 2) {
            return [
                'points' => array_map(fn (): array => ['x' => 0.0, 'y' => 0.0], $vectors),
                'variance' => [0.0, 0.0],
            ];
        }

        $centered = self::center($vectors);
        $gram = self::gram($centered);

        [$u1, $l1] = self::topEigen($gram);
        $deflated = self::deflate($gram, $u1, $l1);
        [$u2, $l2] = self::topEigen($deflated);

        $s1 = sqrt(max($l1, 0.0));
        $s2 = sqrt(max($l2, 0.0));

        $points = [];
        for ($i = 0; $i < $n; $i++) {
            $points[] = ['x' => round($u1[$i] * $s1, 4), 'y' => round($u2[$i] * $s2, 4)];
        }

        $trace = self::trace($gram);
        $variance = $trace > 0 ? [round($l1 / $trace, 4), round($l2 / $trace, 4)] : [0.0, 0.0];

        return ['points' => $points, 'variance' => $variance];
    }

    /**
     * Similarité cosinus entre deux vecteurs (déjà ~normés côté OpenRouter, mais on
     * divise par les normes pour rester exact).
     *
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;

        foreach ($a as $i => $va) {
            $vb = $b[$i] ?? 0.0;
            $dot += $va * $vb;
            $na += $va * $va;
            $nb += $vb * $vb;
        }

        $denom = sqrt($na) * sqrt($nb);

        return $denom > 0 ? $dot / $denom : 0.0;
    }

    /**
     * @param  list<list<float>>  $vectors
     * @return list<list<float>>
     */
    private static function center(array $vectors): array
    {
        $n = count($vectors);
        $d = count($vectors[0]);
        $mean = array_fill(0, $d, 0.0);

        foreach ($vectors as $vec) {
            for ($j = 0; $j < $d; $j++) {
                $mean[$j] += $vec[$j];
            }
        }
        for ($j = 0; $j < $d; $j++) {
            $mean[$j] /= $n;
        }

        return array_map(function (array $vec) use ($mean, $d): array {
            $out = [];
            for ($j = 0; $j < $d; $j++) {
                $out[$j] = $vec[$j] - $mean[$j];
            }

            return $out;
        }, $vectors);
    }

    /**
     * Matrice de Gram n×n : G[i][j] = <xi, xj>.
     *
     * @param  list<list<float>>  $centered
     * @return list<list<float>>
     */
    private static function gram(array $centered): array
    {
        $n = count($centered);
        $g = array_fill(0, $n, array_fill(0, $n, 0.0));

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i; $j < $n; $j++) {
                $dot = 0.0;
                $a = $centered[$i];
                $b = $centered[$j];
                $d = count($a);
                for ($k = 0; $k < $d; $k++) {
                    $dot += $a[$k] * $b[$k];
                }
                $g[$i][$j] = $dot;
                $g[$j][$i] = $dot;
            }
        }

        return $g;
    }

    /**
     * Vecteur propre dominant par itération de la puissance.
     *
     * @param  list<list<float>>  $m
     * @return array{0: list<float>, 1: float}
     */
    private static function topEigen(array $m): array
    {
        $n = count($m);
        $v = array_fill(0, $n, 0.0);
        // Départ déterministe non trivial (évite Math.random, interdit en workflow).
        for ($i = 0; $i < $n; $i++) {
            $v[$i] = sin($i + 1.0);
        }
        $v = self::normalize($v);

        $lambda = 0.0;
        for ($iter = 0; $iter < 100; $iter++) {
            $mv = self::matVec($m, $v);
            $norm = self::norm($mv);

            if ($norm < 1e-12) {
                break;
            }

            $next = array_map(fn (float $x): float => $x / $norm, $mv);
            $lambda = self::dot($next, self::matVec($m, $next));

            if (self::dot($v, $next) > 0.9999999) {
                $v = $next;
                break;
            }
            $v = $next;
        }

        return [$v, $lambda];
    }

    /**
     * Déflation : retire la composante déjà extraite pour trouver la suivante.
     *
     * @param  list<list<float>>  $m
     * @param  list<float>  $u
     * @return list<list<float>>
     */
    private static function deflate(array $m, array $u, float $lambda): array
    {
        $n = count($m);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $m[$i][$j] -= $lambda * $u[$i] * $u[$j];
            }
        }

        return $m;
    }

    /**
     * @param  list<list<float>>  $m
     * @param  list<float>  $v
     * @return list<float>
     */
    private static function matVec(array $m, array $v): array
    {
        $n = count($m);
        $out = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            $sum = 0.0;
            for ($j = 0; $j < $n; $j++) {
                $sum += $m[$i][$j] * $v[$j];
            }
            $out[$i] = $sum;
        }

        return $out;
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private static function dot(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $i => $x) {
            $sum += $x * $b[$i];
        }

        return $sum;
    }

    /**
     * @param  list<float>  $v
     */
    private static function norm(array $v): float
    {
        return sqrt(self::dot($v, $v));
    }

    /**
     * @param  list<float>  $v
     * @return list<float>
     */
    private static function normalize(array $v): array
    {
        $norm = self::norm($v);

        return $norm < 1e-12 ? $v : array_map(fn (float $x): float => $x / $norm, $v);
    }

    /**
     * @param  list<list<float>>  $m
     */
    private static function trace(array $m): float
    {
        $sum = 0.0;
        foreach ($m as $i => $row) {
            $sum += $row[$i];
        }

        return $sum;
    }
}
