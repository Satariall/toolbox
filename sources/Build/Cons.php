<?php
/**
 * @brief      Cons Singleton
 * @copyright  -storm_copyright-
 * @package    IPS Social Suite
 * @subpackage toolbox
 * @since      -storm_since_version-
 * @version    -storm_version-
 */


namespace IPS\toolbox\Build;

use IPS\Application;
use IPS\IPS;
use IPS\Member;
use IPS\Output;
use IPS\Patterns\Singleton;
use IPS\Request;
use IPS\toolbox\Forms;
use function array_merge;
use function constant;
use function defined;
use function gettype;
use function header;
use function implode;
use function in_array;
use function ksort;
use function mb_substr;
use function mb_ucfirst;
use function sleep;

if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) ) {
    header( ( $_SERVER[ 'SERVER_PROTOCOL' ] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
    exit;
}

/**
 * Cons Class
 *
 * @mixin \IPS\toolbox\Build\Cons
 */
class _Cons extends Singleton
{

    /**
     * @brief Singleton Instances
     * @note  This needs to be declared in any child class.
     * @var static
     */
    protected static $instance;

    protected static $importantIPS = [];
    protected static $devTools = [];
    protected $constants;

    public function form()
    {
        $constants = $this->buildConstants();
        $e = [];


        foreach ( $constants as $key => $value ) {
            $tab = mb_ucfirst( mb_substr( $key, 0, 1 ) );

            if ( in_array( $key, static::$importantIPS, \true ) ) {
                $tab = 'Important';
            }

            if ( isset( $value[ 'tab' ] ) ) {
                $tab = $value[ 'tab' ];
            }

            Member::loggedIn()->language()->words[ $tab . '_tab' ] = $tab;
            $e[ $key ] = [
                'name'        => $key,
                'label'       => $key,
                'default'     => $value[ 'current' ],
                'description' => $value[ 'description' ] ?? \null,
                'tab'         => $tab,
            ];

            switch ( gettype( $value[ 'current' ] ) ) {
                case 'boolean':
                    $e[ $key ][ 'class' ] = 'yn';
                    $e[ $key ][ 'default' ] = (bool)$value[ 'current' ];
                    break;
                case 'int':
                    $e[ $key ][ 'class' ] = 'number';
                    break;
            }
        }

        $forms = Forms::execute( [ 'elements' => $e ] );

        if ( $values = $forms->values() ) {
            $this->save( $values, $constants );
            Output::i()->redirect( Request::i()->url(), 'Constants.php Updated!' );
        }

        return $forms;
    }

    protected function buildConstants()
    {
        if ( $this->constants === \null ) {
            $cons = IPS::defaultConstants();
            $first = [];
            $constants = [];
            $important[] = static::$importantIPS;

            foreach ( Application::allExtensions( 'toolbox', 'constants' ) as $extension ) {
                $important[] = $extension->add2Important();
                $extra = $extension->getConstants();
                $first[] = $extra;

                foreach ( $extra as $k => $v ) {
                    static::$devTools[ $k ] = $v[ 'name' ];
                }

            }

            $first = array_merge( ...$first );
            static::$importantIPS = array_merge( ... $important );
            foreach ( $cons as $key => $con ) {
                if ( $key === 'READ_WRITE_SEPARATION' || $key === 'REPORT_EXCEPTIONS' ) {
                    continue;
                }
                $current = constant( '\\IPS\\' . $key );

                $data = [
                    'name'    => $key,
                    'default' => $con,
                    'current' => $current,
                    'type'    => gettype( constant( '\\IPS\\' . $key ) ),
                ];

                if ( in_array( $key, static::$importantIPS, \true ) ) {
                    $first[ $key ] = $data;
                }
                else {
                    $constants[ $key ] = $data;
                }
            }
            ksort( $constants );


            $this->constants = array_merge( $first, $constants );

        }

        return $this->constants;
    }

    public function save( array $values, array $constants )
    {
        $toWrite = [];

        foreach ( Application::allExtensions( 'toolbox', 'constants' ) as $extension ) {
            $extension->formateValues( $values );
        }

        foreach ( $constants as $key => $val ) {
            $data = $values[ $key ];
            switch ( $val[ 'type' ] ) {
                case 'integer':
                case 'boolean':
                    $check = (int)$data;
                    $check2 = (int)$val[ 'default' ];
                    break;
                default:
                    $check2 = (string)$val[ 'default' ];
                    $check = (string)$data;
                    break;
            }
            if ( ( defined( '\\IPS\\' . $key ) && $check !== $check2 ) || in_array( $key, static::$devTools, \true ) ) {

                $dataType = "'" . $data . "'";

                switch ( $val[ 'type' ] ) {
                    case 'integer':
                        $dataType = (int)$data;
                        break;
                    case 'boolean':
                        $dataType = $data ? 'true' : 'false';
                        break;
                }

                $toWrite[] = "\\define('" . $key . "'," . $dataType . ');';
            }
        }
        $toWrite = implode( "\n", $toWrite );
        $fileData = <<<EOF
<?php
{$toWrite}
EOF;
        if ( \IPS\NO_WRITES !== \true ) {
            \file_put_contents( \IPS\ROOT_PATH . '/constants.php', $fileData );
            \opcache_reset();
            sleep( 2 );
        }
    }
}
