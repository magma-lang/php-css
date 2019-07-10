# A CSS Preprocessor for PHP
Its Fast and easy

## Example

Mixins need to go on top.
You can add default mixins in PHP.

```css
@bg-white
	background: #fff

@co-white
	color: #fff

*
	box-sizing: border-box

::root
	--main-var: #fff

.container
	
	.class
		color: --main-var

		:hover, .haha
			@co-white
```

## Usage
```
require_once(  __DIR__. '/engine.php' );

$engine = new MagmaCSS\Engine( __DIR__. '/tmp/', true );
$file = $engine->go( __DIR__. 'css/test.mgcss' );

$absoluteFilePath = __DIR__. '/tmp/'. $file;
```
