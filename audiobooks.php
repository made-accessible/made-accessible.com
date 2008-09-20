<?php
if( $download = trim($_REQUEST['download']) )
	download($download);
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Audio Book Search</title>
</head>
<h1>Search for audio books</h1>
<body>
<?php

// force use of local proxy by underlying HTTP API requests

libxml_set_streams_context(
	stream_context_get_default(	array(
		'http' => array(
			'request_fulluri' => true,
			'proxy' => 'tcp://localhost:8080'
		)
	)	)
);

// Present form for searching Librivox

if( !($terms = trim($_REQUEST['terms'])) )
	form();
else
  search($terms);

function form()
{
?>

<form>
<fieldset>
	<label>Search catalogue for
		<input type="text" name="terms"/>
		<input type="submit" name="search" value="search"/>
	</label>
</fieldset>
</form>
<?php
}

function search($terms)
{
	$results = array();
	$total = 0;

	// ask librivox for results
	$search_params = array(
		'q' => '('.$terms.') AND format:MP3',
		'rows' => 5,
		'fl' => array('identifier','creator','title','subject'),
		'fmt' => 'json',
		'xmlsearch' => 'Search'
	);
	$page = json_decode(
		file_get_contents(
			'http://www.archive.org/advancedsearch.php?'
			.http_build_query($search_params)
		)
	);

	$total = $page->response->numFound;
	$results = $page->response->docs;
?>
<h2>For
<var
	title="<?php echo htmlspecialchars($page->responseHeader->params->q) ?>">
<?php echo $terms ?></var>,
showing <var><?php echo count($results) ?></var>
results<?php if( $total != count($results) ) echo ' out of <var>', $total, '</var>' ?>.
</h2>
<ol id="results">
	<?php render($results) ?>
</ol>
<?php
}

function render($results)
{
	foreach($results as $result)
	{
		echo '<li>';
			//var_dump($result);
			echo '<a href="?download='
						, $result->identifier
						, '">';
			echo '<var>',$result->title,'</var>';
			echo '</a>';
			if( isset($result->creator) && count($result->creator) )
			{
				echo ' by ';
				echo '<var>',$result->creator[0],'</var>';
				if( count($result->creator) > 1 ) echo '...';
			}
		echo '</li>';
	}
}

function download($identifier)
{
	$base_url = "http://www.archive.org/download/$identifier/";

	$files = new SimpleXMLElement(
		"$base_url{$identifier}_files.xml"
	, LIBXML_COMPACT
	, true
	);

	$xpaths = array(
		// first, scan for original mp3 files
		'file[@source="original" and (contains(format, "mp3") or contains(format, "MP3"))]'
		// next, scan for 64kbps derivative mp3 files
		, 'file[@source="derivative" and bitrate=64 and (contains(format, "mp3") or contains(format, "MP3"))]'
	);

//	echo '<pre>';
//	print_r($files);

	$mp3_files = $files->xpath( current($xpaths) );
//	print_r( $mp3_files );

	if( empty($mp3_files) )
		$mp3_files = $files->xpath( next($xpaths) );
//	print_r( $mp3_files );

//	echo '</pre>';

	usort( $mp3_files, create_function( '$a,$b', '
		return
			($a->track && $b->track)
			? ($a->track - $b->track)
			: stricmp($a->title, $b->title)
		;
	' ) );

//	if( count($original_files) == 1 )
//	{
//		header( "Location: $base_url{$original_files[0][name]}" );
//	}
	if( count($mp3_files) )
	{
		echo "<h2>mp3 files to download</h2>";
		echo "<ol id='mp3-files'>";
		foreach( $mp3_files as $file )
		{
			$title = $file->title ? $file->title : 'Untitled';
			//echo "$base_url{$file[name]}\n";
			echo "<li><a href='$base_url{$file[name]}'>$title</a></li>";
		}
		echo "</ol>";
	}
	else
	{
		echo "<h2>No mp3 files found</h2>";
		//var_dump($files);
	}
	die();
}
?>
</body>
</html>