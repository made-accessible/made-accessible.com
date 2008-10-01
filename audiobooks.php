<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Audio Book Search</title>
</head>
<h1>Search for audio books</h1>
<body>
<?php



// Copyright © 2008 made-accessible.com Some rights reserved.
// Creative Commons Attribution-Noncommercial-Share Alike 2.0 UK: England & Wales License
// http://creativecommons.org/licenses/by-nc-sa/2.0/uk/
//
// Authors: Paul Mitchell and Lucy Buykx <geeks@made-accessible.com>



// uncomment to force use of local proxy by underlying HTTP API requests
/*
libxml_set_streams_context(
	stream_context_get_default(	array(
		'http' => array(
			'request_fulluri' => true,
			'proxy' => 'tcp://localhost:8080'
		)
	)	)
);
*/


if( $download = trim($_REQUEST['download']) )
	download($download);

// Present form for searching catalogue

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

	$rows = (integer)$_REQUEST['rows'];
	$last_result = (integer)$_REQUEST['last'];

	if( !$rows ) {
		$rows = 5;
		$last_result = 5;
	}

	// ask Internet Archive for results
	$search_params = array(
		'q' => '('.$terms.') AND format:MP3',
		'rows' => $rows,
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
	$results_remaining = ($total - count($results));
?>
<h2>For
<var
	title="<?php echo htmlspecialchars($page->responseHeader->params->q) ?>">
<?php echo $terms ?></var>,
showing <var><?php echo count($results) ?></var>
results<?php if( $results_remaining ) echo ' out of <var>', $total, '</var>' ?>.
</h2>
<ol id="results">
	<?php render($results, $last_result) ?>
</ol>
<?php
	if( $results_remaining ) {
		$next = (($results_remaining < 5) ? $results_remaining : 5);
		echo '<p><a href="?terms='.urlencode($terms).'&amp;rows='.($rows+$next).'&amp;last='.$rows.'#next">Next '.$next.' results</a></p>';
	}
}

function render($results, $last = null)
{
	foreach($results as $index => $result)
	{
		$id = ($last == $index) ? ' id="next"' : '';

		echo "<li$id>";
			echo '<a href="?download='
						, $result->identifier
						, '&amp;title='
						, urlencode($result->title)
						, '">';
			echo '<var>',$result->title,'</var>';
			echo '</a>';
			if( isset($result->creator) && count($result->creator) )
			{
				echo ' by ';
				echo '<var>',$result->creator[0],'</var>';
				if( count($result->creator) > 1 ) echo '...';
			}
		echo '</li>'."\n";
	}
}

function file_order( $a, $b )
{
	return
		($a->track && $b->track)
		? ($a->track - $b->track)
		: ($a->title && $a->title != $b->title)
			? strcasecmp($a->title, $b->title)
			: strnatcasecmp($a->name, $b->name)
	;
}

function download($identifier)
{
	$base_url = "http://www.archive.org/download/$identifier/";

	$files = new SimpleXMLElement(
		"$base_url{$identifier}_files.xml"
	, LIBXML_COMPACT
	, true
	);

	// prefer original mp3 files
	$mp3_files = $files->xpath(
		'file[
			@source="original"
			and
			(contains(format, "mp3") or contains(format, "MP3"))
		]'
 	);

	if( empty($mp3_files) ) {
		// fall back to 64kbps derivative mp3 files
		$mp3_files = $files->xpath(
			'file[
				@source="derivative"
				and
				bitrate=64
				and
				(contains(format, "mp3") or contains(format, "MP3"))
			]'
		);
	}

	if( count($mp3_files) )
	{
		usort( $mp3_files, 'file_order' );

		$replacement_title =
			$_REQUEST['title']
			? $_REQUEST['title']
			: 'Untitled'
		;

		echo "<h2>mp3 files to download</h2>";
		echo "<ol id='mp3-files'>";
		foreach( $mp3_files as $file )
		{
			$title = $file->title ? $file->title : $replacement_title;

			echo "<li><a href='$base_url{$file[name]}'>$title</a></li>";
		}
		echo "</ol>";
	}
	else
	{
		echo "<h2>No mp3 files found</h2>";
	}
}
?>
</body>
</html>