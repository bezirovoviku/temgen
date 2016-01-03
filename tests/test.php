<?php
require_once('../src/Temgen/Generator.php');
require_once('../src/Temgen/Generator/Docx.php');
require_once('../src/Temgen/Generator/Filter.php');
require_once('../src/Temgen/Generator/Filters/FormatFilter.php');
require_once('../src/Temgen/Generator/Filters/Upper.php');
require_once('../src/Temgen/Generator/Filters/Lower.php');
require_once('../src/Temgen/Generator/Filters/Date.php');
require_once('../src/Temgen/Generator/Filters/Number.php');
require_once('../src/Temgen/Converter.php');
require_once('../src/Temgen/Converter/Batch.php');
require_once('../src/Temgen/Converter/PPDF.php');
require_once('../src/Temgen/Converter/OPDF.php');
require_once('../src/Temgen/ParseException.php');
require_once('../src/Temgen/Document.php');
require_once('../src/Temgen/Document/Docx.php');

$input_data = array(
	array(
		'condition' => true,
		'nadpis' => 'TEXT',
		'number' => 1564727.564,
		'date' => '2015-11-05 11:30',
		'format' => 'd.m.Y H:i:s',
		'items' => array(
			array('name' => 'Test 1'),
			array('name' => 'Test 2')
		)
	),
	array(
		'condition' => false,
		'nadpis' => 'TEXT 2',
		'number' => '135486753132',
		'date' => time(),
		'format' => 'd.m.Y H:i:s',
		'items' => array(
			array('name' => 'Test 85'),
			array('name' => 'Text 2')
		)
	)
);

$archive = 'tmp/archive-txt.zip';
if (file_exists($archive))
	unlink($archive);

$generator = new Temgen\Generator();
$generator->addFilters();
$generator->setTemplate(new Temgen\Document('data/template.html'));
$generator->setTmp('./tmp/');
$generator->generateArchive($input_data, $archive, new Temgen\Converter\PPDF);

$archive = 'tmp/archive-docx.zip';
if (file_exists($archive))
	unlink($archive);

$generator = new Temgen\Generator\Docx();
$generator->addFilters();
$generator->setTemplate(new Temgen\Document\Docx('data/template.docx'));
$generator->setTmp('./tmp/');
$generator->generateArchive($input_data, $archive, new Temgen\Converter\OPDF);