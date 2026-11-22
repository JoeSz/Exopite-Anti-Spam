<?php

/**
 * SVG path rewriting of the image captcha icons: the same shape, different markup on every render.
 *
 * @link       https://www.joeszalai.org
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Parses an SVG path ("d") into absolute subpaths, changes how the shape is written without
 * changing the shape, and writes it again:
 *   - subpaths in random order (fill rule nonzero: the order does not matter)
 *   - closed subpaths start at a random corner (same direction, so holes stay holes)
 *   - segments split in two (de Casteljau), lines written as curves, quadratic curves as cubic
 *   - an affine transform (rotation, scale, shear, offset) applied to the points themselves
 *   - absolute / relative commands, H/V, repeated commands and the precision mixed at random
 * So neither the command letters nor the numbers match the icon set.
 *
 * PHP 7.0 compatible (no arrow functions, no void / nullable types).
 *
 * Subpath: array( 'start' => array( x, y ), 'closed' => bool, 'segs' => array(
 *          array( 'L', array( x, y ) ) | array( 'Q', array( cx, cy ), array( x, y ) )
 *          | array( 'C', array( c1x, c1y ), array( c2x, c2y ), array( x, y ) ), ... ) )
 */
class Exopite_Anti_Spam_Svg_Path {

    const TOKENS = '/[MmLlHhVvCcSsQqTtAaZz]|[-+]?(?:\d*\.\d+|\d+\.?)(?:[eE][-+]?\d+)?/';

    /**
     * Subpaths of a path, false when the path has arcs or can not be read.
     *
     * @param string $d Path data.
     * @return array|false
     */
    public static function parse( $d ) {

        $params = array( 'M' => 2, 'L' => 2, 'H' => 1, 'V' => 1, 'C' => 6, 'S' => 4, 'Q' => 4, 'T' => 2, 'A' => 7, 'Z' => 0 );

        if ( ! preg_match_all( self::TOKENS, $d, $m ) ) {
            return false;
        }

        $tokens = $m[0];
        $subs   = array();
        $sub    = null;
        $cur    = array( 0.0, 0.0 );
        $start  = array( 0.0, 0.0 );
        $last_q = null; // Control point of the previous Q/T (reflection of T).
        $last_c = null; // Second control point of the previous C/S (reflection of S).
        $cmd    = '';
        $i      = 0;
        $n      = count( $tokens );

        while ( $i < $n ) {

            if ( ctype_alpha( $tokens[ $i ] ) ) {
                $cmd = $tokens[ $i ];
                $i++;
            } elseif ( '' === $cmd || 'Z' === strtoupper( $cmd ) ) {
                return false; // Numbers without a command.
            }

            $upper = strtoupper( $cmd );

            if ( 'A' === $upper ) {
                return false;
            }

            $rel = ( $cmd !== $upper );

            if ( 'Z' === $upper ) {
                if ( null !== $sub ) {
                    $sub['closed'] = true;
                    $subs[]        = $sub;
                    $sub           = null;
                }
                $cur    = $start;
                $last_q = null;
                $last_c = null;
                continue;
            }

            $count = $params[ $upper ];
            if ( $i + $count > $n ) {
                return false;
            }

            $p = array();
            for ( $k = 0; $k < $count; $k++ ) {
                if ( ctype_alpha( $tokens[ $i ] ) ) {
                    return false;
                }
                $p[] = (float) $tokens[ $i ];
                $i++;
            }

            if ( 'M' === $upper ) {
                if ( null !== $sub ) {
                    $subs[] = $sub;
                }
                $cur    = self::point( $rel, $cur, $p[0], $p[1] );
                $start  = $cur;
                $sub    = array( 'start' => $cur, 'closed' => false, 'segs' => array() );
                $cmd    = $rel ? 'l' : 'L'; // Further pairs are lines.
                $last_q = null;
                $last_c = null;
                continue;
            }

            // Drawing after Z without M: a new subpath at the current point.
            if ( null === $sub ) {
                $sub = array( 'start' => $cur, 'closed' => false, 'segs' => array() );
            }

            switch ( $upper ) {
                case 'L':
                    $seg = array( 'L', self::point( $rel, $cur, $p[0], $p[1] ) );
                    break;
                case 'H':
                    $seg = array( 'L', array( $rel ? $cur[0] + $p[0] : $p[0], $cur[1] ) );
                    break;
                case 'V':
                    $seg = array( 'L', array( $cur[0], $rel ? $cur[1] + $p[0] : $p[0] ) );
                    break;
                case 'Q':
                    $seg = array( 'Q', self::point( $rel, $cur, $p[0], $p[1] ), self::point( $rel, $cur, $p[2], $p[3] ) );
                    break;
                case 'T':
                    $control = $last_q ? array( 2 * $cur[0] - $last_q[0], 2 * $cur[1] - $last_q[1] ) : $cur;
                    $seg     = array( 'Q', $control, self::point( $rel, $cur, $p[0], $p[1] ) );
                    break;
                case 'C':
                    $seg = array( 'C', self::point( $rel, $cur, $p[0], $p[1] ), self::point( $rel, $cur, $p[2], $p[3] ), self::point( $rel, $cur, $p[4], $p[5] ) );
                    break;
                default: // S.
                    $control = $last_c ? array( 2 * $cur[0] - $last_c[0], 2 * $cur[1] - $last_c[1] ) : $cur;
                    $seg     = array( 'C', $control, self::point( $rel, $cur, $p[0], $p[1] ), self::point( $rel, $cur, $p[2], $p[3] ) );
            }

            $sub['segs'][] = $seg;
            $last_q        = ( 'Q' === $seg[0] ) ? $seg[1] : null;
            $last_c        = ( 'C' === $seg[0] ) ? $seg[2] : null;
            $cur           = $seg[ count( $seg ) - 1 ];
        }

        if ( null !== $sub ) {
            $subs[] = $sub;
        }

        return $subs;
    }

    private static function point( $rel, $cur, $x, $y ) {
        return $rel ? array( $cur[0] + $x, $cur[1] + $y ) : array( $x, $y );
    }

    /**
     * Every point through the affine matrix array( a, b, c, d, e, f ):
     * x' = a*x + c*y + e, y' = b*x + d*y + f.
     */
    public static function transform( $subs, $m ) {

        foreach ( $subs as $s => $sub ) {
            $subs[ $s ]['start'] = self::apply( $m, $sub['start'] );
            foreach ( $sub['segs'] as $g => $seg ) {
                for ( $k = 1, $c = count( $seg ); $k < $c; $k++ ) {
                    $subs[ $s ]['segs'][ $g ][ $k ] = self::apply( $m, $seg[ $k ] );
                }
            }
        }

        return $subs;
    }

    private static function apply( $m, $p ) {
        return array( $m[0] * $p[0] + $m[2] * $p[1] + $m[4], $m[1] * $p[0] + $m[3] * $p[1] + $m[5] );
    }

    /**
     * Same shape, other structure (order, start corners, split and raised segments).
     */
    public static function shuffle( $subs ) {

        foreach ( $subs as $s => $sub ) {

            if ( empty( $sub['segs'] ) ) {
                continue;
            }

            // Split / raise segments.
            $out = array();
            $p0  = $sub['start'];
            foreach ( $sub['segs'] as $seg ) {
                foreach ( self::vary( $p0, $seg ) as $part ) {
                    $out[] = $part;
                }
                $p0 = $seg[ count( $seg ) - 1 ];
            }
            $sub['segs'] = $out;

            // Closed: start at another corner (the closing line made explicit first).
            if ( $sub['closed'] && count( $sub['segs'] ) > 1 ) {

                $last = $sub['segs'][ count( $sub['segs'] ) - 1 ];
                $end  = $last[ count( $last ) - 1 ];

                if ( abs( $end[0] - $sub['start'][0] ) > 1e-9 || abs( $end[1] - $sub['start'][1] ) > 1e-9 ) {
                    $sub['segs'][] = array( 'L', $sub['start'] );
                }

                $k = mt_rand( 0, count( $sub['segs'] ) - 1 );
                if ( $k > 0 ) {
                    $prev         = $sub['segs'][ $k - 1 ];
                    $sub['start'] = $prev[ count( $prev ) - 1 ];
                    $sub['segs']  = array_merge( array_slice( $sub['segs'], $k ), array_slice( $sub['segs'], 0, $k ) );
                }
            }

            $subs[ $s ] = $sub;
        }

        $subs = array_values( $subs );
        shuffle( $subs );

        return $subs;
    }

    /**
     * One segment as 1-2 segments of the same curve.
     */
    private static function vary( $p0, $seg ) {

        // Raise the degree sometimes (exact): line -> quadratic, quadratic -> cubic.
        if ( 'L' === $seg[0] && mt_rand( 1, 100 ) <= 20 ) {
            $seg = array( 'Q', self::lerp( $p0, $seg[1], mt_rand( 20, 80 ) / 100 ), $seg[1] );
        } elseif ( 'Q' === $seg[0] && mt_rand( 1, 100 ) <= 25 ) {
            $seg = array( 'C', self::lerp( $p0, $seg[1], 2 / 3 ), self::lerp( $seg[2], $seg[1], 2 / 3 ), $seg[2] );
        }

        if ( mt_rand( 1, 100 ) > 35 ) {
            return array( $seg );
        }

        // Split at t (de Casteljau).
        $t = mt_rand( 30, 70 ) / 100;

        if ( 'L' === $seg[0] ) {
            return array( array( 'L', self::lerp( $p0, $seg[1], $t ) ), array( 'L', $seg[1] ) );
        }

        if ( 'Q' === $seg[0] ) {
            $a = self::lerp( $p0, $seg[1], $t );
            $b = self::lerp( $seg[1], $seg[2], $t );
            return array( array( 'Q', $a, self::lerp( $a, $b, $t ) ), array( 'Q', $b, $seg[2] ) );
        }

        $a  = self::lerp( $p0, $seg[1], $t );
        $b  = self::lerp( $seg[1], $seg[2], $t );
        $c  = self::lerp( $seg[2], $seg[3], $t );
        $ab = self::lerp( $a, $b, $t );
        $bc = self::lerp( $b, $c, $t );

        return array( array( 'C', $a, $ab, self::lerp( $ab, $bc, $t ) ), array( 'C', $bc, $c, $seg[3] ) );
    }

    private static function lerp( $a, $b, $t ) {
        return array( $a[0] + ( $b[0] - $a[0] ) * $t, $a[1] + ( $b[1] - $a[1] ) * $t );
    }

    /**
     * Path data with random notation.
     *
     * @param array $subs      Subpaths.
     * @param float $jitter    Max. random move of every point (units).
     * @param array $precision Min. and max. decimals.
     * @return string
     */
    public static function write( $subs, $jitter = 0.0, $precision = array( 1, 3 ) ) {

        $out  = '';
        $last = '';
        $cur  = array( 0.0, 0.0 );

        foreach ( $subs as $sub ) {

            if ( empty( $sub['segs'] ) ) {
                continue;
            }

            $start = self::jitter( $sub['start'], $jitter );
            $rel   = ( '' !== $out && 1 === mt_rand( 0, 1 ) );
            self::emit( $out, $last, $rel ? 'm' : 'M', $rel ? array( $start[0] - $cur[0], $start[1] - $cur[1] ) : $start, $precision );
            $cur = $start;

            foreach ( $sub['segs'] as $seg ) {

                $pts = array();
                for ( $k = 1, $c = count( $seg ); $k < $c; $k++ ) {
                    $pts[] = self::jitter( $seg[ $k ], $jitter );
                }
                $end = $pts[ count( $pts ) - 1 ];
                $rel = ( 1 === mt_rand( 0, 1 ) );

                if ( 'L' === $seg[0] && abs( $end[1] - $cur[1] ) < 0.05 && 1 === mt_rand( 0, 1 ) ) {
                    $end[1] = $cur[1];
                    self::emit( $out, $last, $rel ? 'h' : 'H', array( $rel ? $end[0] - $cur[0] : $end[0] ), $precision );
                } elseif ( 'L' === $seg[0] && abs( $end[0] - $cur[0] ) < 0.05 && 1 === mt_rand( 0, 1 ) ) {
                    $end[0] = $cur[0];
                    self::emit( $out, $last, $rel ? 'v' : 'V', array( $rel ? $end[1] - $cur[1] : $end[1] ), $precision );
                } else {
                    $flat = array();
                    foreach ( $pts as $p ) {
                        $flat[] = $rel ? $p[0] - $cur[0] : $p[0];
                        $flat[] = $rel ? $p[1] - $cur[1] : $p[1];
                    }
                    self::emit( $out, $last, $rel ? strtolower( $seg[0] ) : $seg[0], $flat, $precision );
                }

                $cur = $end;
            }

            if ( $sub['closed'] ) {
                $out .= mt_rand( 0, 1 ) ? 'z' : 'Z';
                $last = '';
                $cur  = $start;
            }
        }

        return $out;
    }

    /**
     * Command letter (left out sometimes when it repeats) and numbers.
     */
    private static function emit( &$out, &$last, $letter, $values, $precision ) {

        $repeat = ( $letter === $last && 'M' !== $letter && 'm' !== $letter && 1 === mt_rand( 0, 1 ) );

        $numbers = array();
        foreach ( $values as $value ) {
            $numbers[] = self::number( $value, $precision );
        }

        $out .= ( $repeat ? ' ' : $letter ) . implode( ' ', $numbers );
        $last = $letter;
    }

    private static function number( $value, $precision ) {

        $formatted = rtrim( rtrim( number_format( $value, mt_rand( $precision[0], $precision[1] ), '.', '' ), '0' ), '.' );

        return ( '-0' === $formatted || '' === $formatted ) ? '0' : $formatted;
    }

    private static function jitter( $p, $jitter ) {

        if ( $jitter <= 0 ) {
            return $p;
        }

        return array( $p[0] + mt_rand( -1000, 1000 ) / 1000 * $jitter, $p[1] + mt_rand( -1000, 1000 ) / 1000 * $jitter );
    }

    /**
     * Nonzero winding number of point (x, y), curves flattened to $steps lines.
     * For tests: two paths are the same shape when they agree on a grid of points.
     */
    public static function winding( $subs, $x, $y, $steps = 16 ) {

        $w = 0;

        foreach ( $subs as $sub ) {

            $pts = array( $sub['start'] );
            $p0  = $sub['start'];

            foreach ( $sub['segs'] as $seg ) {
                $n = ( 'L' === $seg[0] ) ? 1 : $steps;
                for ( $s = 1; $s <= $n; $s++ ) {
                    $pts[] = self::at( $p0, $seg, $s / $n );
                }
                $p0 = $seg[ count( $seg ) - 1 ];
            }

            $pts[] = $sub['start']; // Fill closes every subpath.

            for ( $i = 0, $c = count( $pts ) - 1; $i < $c; $i++ ) {
                $ax    = $pts[ $i ][0];
                $ay    = $pts[ $i ][1];
                $bx    = $pts[ $i + 1 ][0];
                $by    = $pts[ $i + 1 ][1];
                $cross = ( $bx - $ax ) * ( $y - $ay ) - ( $x - $ax ) * ( $by - $ay );
                if ( $ay <= $y ) {
                    if ( $by > $y && $cross > 0 ) {
                        $w++;
                    }
                } elseif ( $by <= $y && $cross < 0 ) {
                    $w--;
                }
            }
        }

        return $w;
    }

    /** Point of a segment at t */
    private static function at( $p0, $seg, $t ) {

        if ( 'L' === $seg[0] ) {
            return $seg[1];
        }

        if ( 'Q' === $seg[0] ) {
            return self::lerp( self::lerp( $p0, $seg[1], $t ), self::lerp( $seg[1], $seg[2], $t ), $t );
        }

        $ab = self::lerp( self::lerp( $p0, $seg[1], $t ), self::lerp( $seg[1], $seg[2], $t ), $t );
        $bc = self::lerp( self::lerp( $seg[1], $seg[2], $t ), self::lerp( $seg[2], $seg[3], $t ), $t );

        return self::lerp( $ab, $bc, $t );
    }

}
