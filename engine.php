<?php
/*
@package: Magma PHP Minifier for JS and CSS
@author: Sören Meier <info@s-me.ch>
@version: 0.1 <2019-07-05>
@docs: minifier.magma-lang.com/php/docs/
*/

namespace MagmaCss;

use \Error;

class Engine {

	public $debug = false;

	protected $tmpPath = '';

	protected $globalMixins = [];

	public function __construct( string $tmpPath, bool $debug = false ) {

		$this->tmpPath = $tmpPath;
		$this->debug = $debug;
		if ( !is_dir( $this->tmpPath ) )
			mkdir( $this->tmpPath );

		$this->defaultMixins();

	}

	public function go( string $file ) {

		$filename = md5( $file ). '.css';
		$out = $this->tmpPath. $filename;

		if ( is_file( $out ) && !$this->debug )
			return $filename;

		$str = file_get_contents( $file );
		$str = $this->parse( $str );

		file_put_contents( $out, $str );
		return $filename;


	}

	protected function defaultMixins() {

		// Position
		$this->addMixin( 'fixed', 'position: fixed' );
		$this->addMixin( 'relative', 'position: relative' );
		$this->addMixin( 'absolute', 'position: absolute' );

		// Display
		$this->addMixin( 'block', 'display: block' );
		$this->addMixin( 'none', 'display: none' );
		$this->addMixin( 'flex', 'display: flex' );
		$this->addMixin( 'grid', 'display: grid' );

		// borderbox
		$this->addMixin( 'border-box', 'box-sizing: border-box' );

		// abs center
		$this->addMixin( 'abs-center', [
			'position: absolute',
			'top: 50%',
			'left: 50%',
			'transform: translate(-50%, -50%)'
		] );

		// flex-center
		$this->addMixin( 'flex-center', [
			'display: flex',
			'align-items: center',
			'justify-content: center'
		] );

		// clearfix
		$this->addMixin( 'clearfix', [
			'content: \'\'',
			'display: table',
			'clear: both'
		] );

		// core
		$this->addMixin( 'core', [
			'margin: 0',
			'padding: 0',
			'box-sizing: border-box'
		] );

	}

	// the properties need to be css valid expect (simicolon)
	public function addMixin( string $name, $props ) {
		if ( !is_array( $props ) )
			$props = [$props];
		$this->globalMixins[$name] = $props;
	}

	public function parse( string $str ) {

		$str = rtrim( str_replace( "\r", '', $str ) );
		$str = preg_replace( '/(\/\*.*?\*\/)|(^\s*\/\/.*?$)/m', '', $str );
		// $str = preg_replace( '/^\s*\/\/.*?$/m', '', $str );
		$lines = explode( "\n", $str );


		$inMixins = false;
		$actMix = '';
		$mixins = $this->globalMixins;

		$inSelect = false;
		$selTree = [];
		$selLevel = 0;

		$selectors = [];

		foreach ( $lines as $num => $line ) {

			// if empty line skip
			if ( preg_match( '/^\s*$/', $line ) )
				continue;

			$level = $this->countLevel( $line );

			// Properties
			$prop = preg_match( '/^.*:\s.*$/', $line ) > 0;
			if ( $prop ) {
				$ctn = trim( $line );
				$ctn = preg_replace( '/(?<=:\s)(--[a-zA-Z0-9\-]*)/m', 'var($0)', $ctn );

				if ( $inMixins )
					$mixins[$actMix][] = trim( $line );
				else if ( $inSelect ) {

					// prop in select
					$sel = $this->buildSelector( $selTree, $level );

					if ( !isset( $selectors[$sel] ) )
						$selectors[$sel] = [];

					$selectors[$sel][] = $ctn;

				} else
					throw new Error( sprintf( 'No selector or mixin before line %d: %s', $num, $line ) );

				continue;
			}

			// Mixins
			$defMixin = preg_match( '/^\s*@([a-zA-Z][\w\-]*)\s*$/', $line, $mixinName ) > 0;
			if ( $defMixin ) {
				$mixinName = $mixinName[1];

				if ( $inMixins && $level > 0 ) {

					if ( !isset( $mixins[$mixinName] ) )
						throw new Error( sprintf( 'Could not find mixin %s, on line %d', $mixinName, $num ) );

					$mixins[$actMix] = array_merge( $mixins[$actMix], $mixins[$mixinName] );

				} else if ( $inSelect ) {

					if ( !isset( $mixins[$mixinName] ) )
						throw new Error( sprintf( 'Could not find mixin %s, on line %d', $mixinName, $num ) );

					// prop in select
					$sel = $this->buildSelector( $selTree, $level );

					if ( !isset( $selectors[$sel] ) )
						$selectors[$sel] = [];

					$selectors[$sel] = array_merge( $selectors[$sel], $mixins[$mixinName] );
					/*var_dump( 'prop inSelect', $sels );
					die;*/

				} else {
					$inMixins = true;
					$inSelect = false;
					$actMix = $mixinName;
					$mixins[$actMix] = [];
				}

				continue;

			}

			// else we have a selector
			$inSelect = true;
			$inMixins = false;
			$sel = trim( $line );
			// should split by comma
			$selTree[$level] = trim( $line );


			// short media
			// echo $line. "\n";



			// if (  )

			// $tree

			// state

		}

		/*var_dump( $mixins );
		var_dump( $selectors );*/

		//

		return $this->buildFromSelectors( $selectors );

	}

	protected function buildSelector( array $inTree, int $level ) {

		$tree = [];
		foreach ( array_slice( $inTree, 0, $level ) as $tr )
			$tree[] = array_map( 'trim', explode( ',', $tr ) );

		$sels = array_shift( $tree );
		foreach ( $tree as $tr ) {

			$nSels = [];
			foreach ( $tr as $t )
				foreach ( $sels as $sel )
					$nSels[] = sprintf( '%s%s%s', $sel, $t[0] === ':' ? '' : ' ', $t );

			$sels = $nSels;

		}

		return implode( ",\n", $sels );

	}

	protected function countLevel( string $line ) {
		$count = 0;
		$len = strlen( $line );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $line[$i];
			if ( $c !== "\t" )
				break;
			$count++;
		}
		return $count;
	}

	public function buildFromSelectors( array $selectors ) {

		$str = '';
		foreach ( $selectors as $sel => $props ) {
			$str .= $sel. ' {'. "\n";
			foreach ( $props as $prop )
				$str .= "\t". $prop. ";\n";
			$str .= "}\n\n";
		}

		return $str;

	}

}