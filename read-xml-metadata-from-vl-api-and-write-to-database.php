<?php
// PHP (Vers. 7.2) - Skript zum Auslesen von Metadaten von PDFs, die den Identifier im Dateinamen enthalten (letzte Ziffer vor der Dateiendung),
// zum Umbenennen und Verschieben dieser Daten in ein anderes Verzeichnis sowie zum Konvertieren und Eintragen der Daten in eine MySQL-Datenbank
// geeignet für die XML-Datenquelle: https://sammlungen.ub.uni-frankfurt.de/oai/
// das Skript wirft die Liste der "identifier" aus
// Dieses Skript kann frei verwendet und nach Belieben abgeändert werden.

// V.Teuschler - 15.04.2021
// MIT-Licence
// Quelle: https://github.com/FID-Biodiversity/visual-library-metadata-exporter

// Variablen definieren
$quellordner = "quellordner"; // Quellordnetr definieren, in dem die Ausgangsdaten (PDFs) liegen
$zielordner = "zielordner"; // Zielordner, wohin die umbenannten Dateien verschoben werden sollen
$dateiprefix = "dateiprefix"; // Präfix für die neuen Dateinamen, z.B. Name der Publikationsreihe

// Umschreibung der Bytes auf KB bzw. MB
function FileSizeConvert($bytes)
{
    $bytes = floatval($bytes);
        $arBytes = array(
            0 => array(
                "UNIT" => "TB",
                "VALUE" => pow(1024, 4)
            ),
            1 => array(
                "UNIT" => "GB",
                "VALUE" => pow(1024, 3)
            ),
            2 => array(
                "UNIT" => "MB",
                "VALUE" => pow(1024, 2)
            ),
            3 => array(
                "UNIT" => "KB",
                "VALUE" => 1024
            ),
            4 => array(
                "UNIT" => "B",
                "VALUE" => 1
            ),
        );

    foreach($arBytes as $arItem)
    {
        if($bytes >= $arItem["VALUE"])
        {
            $result = $bytes / $arItem["VALUE"];
            $result = str_replace(".", "," , strval(round($result, 2)))." ".$arItem["UNIT"];
            break;
        }
    }
    return $result;
}

// Datenbankverbindung herstellen, entsprechende Werte eintragen
error_reporting(E_ALL);
define ( 'MYSQL_HOST',      '' );
define ( 'MYSQL_BENUTZER',  '' );
define ( 'MYSQL_KENNWORT',  '' );
define ( 'MYSQL_DATENBANK', '' );

$db_link = mysqli_connect (
                     MYSQL_HOST, 
                     MYSQL_BENUTZER, 
                     MYSQL_KENNWORT, 
                     MYSQL_DATENBANK
                    );

$verzeichnis = "./".$quellordner;

if ( is_dir ( $verzeichnis ))
{
    // öffnen des Verzeichnisses
    if ( $handle = opendir($verzeichnis) )
    {
        // einlesen der Verzeichnisses
        while (($file = readdir($handle)) !== false)
        {
            $aktdatei = $quellordner."/".$file;
            $file_size = filesize($aktdatei); // Dateigröße ermitteln
            $file_size = FileSizeConvert($file_size); // Funktion Umschreibung der Bytes auf KB bzw. MB
			$idnr = substr($file, -11, 7); // identifier aus Dateinamen extrahieren

            if (!is_dir($file)) {

                // Aufruf des XML-Links, namespace=dc
                $xml_dc = "";
                $dc_link = "https://sammlungen.ub.uni-frankfurt.de/oai/?verb=GetRecord&metadataPrefix=oai_dc&identifier=".$idnr; 
                $xml_dc = simplexml_load_file($dc_link);
                $xml_dc->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/'); // Namespace registrieren

                $titel_dc = ($xml_dc->xpath('//dc:title'));
                $titeleintrag = "Ohne Titel";
                if (isset($titel_dc[0])) $titeleintrag = $titel_dc[0]; // Titel

                $authors_dc = ($xml_dc->xpath('//dc:description'));
                $autoreneintrag2 = "keinen Eintrag";
                if (isset($authors_dc[0])) $autoreneintrag2 = $authors_dc[0]; // zur Kontrolle der Autorenbezeichnung
                $year_dc = ($xml_dc->xpath('//dc:date'));
                $jahreintrag = "0";
                if (isset($year_dc[0])) $jahreintrag = $year_dc[0]; // Erscheinungsjahr

                $text_lang_dc = ($xml_dc->xpath('//dc:language'));
                $text_lang_dc_eintrag = "";
                if (isset($text_lang_dc[0])) $text_lang_dc_eintrag = $text_lang_dc[0]; // Publikationssprache
                                
                // Aufruf des XML-Links, namespace=mods                
                $xml_mods = "";
                $mods_link = "https://sammlungen.ub.uni-frankfurt.de/oai/?verb=GetRecord&metadataPrefix=mods&identifier=".$idnr;
                $xml_mods = simplexml_load_file($mods_link);
                $xml_mods->registerXPathNamespace('mods', 'http://www.loc.gov/mods/v3'); // Namespace registrieren

                $p1 = ($xml_mods->xpath('//mods:start'));
                $p1eintrag = "";
                if (isset($p1[0])) $p1eintrag = $p1[0]; // Paginierung Start

                $p2 = ($xml_mods->xpath('//mods:end'));
                $p2eintrag = "";
                if (isset($p2[0])) $p2eintrag = $p2[0]; // Paginierung Ende
                $nr = ($xml_mods->xpath('//mods:number'));
                $nreintrag = $titel_dc;
                if (isset($nr[0])) $nreintrag = $nr[0]; // Band Nr.
                $nreintrag = str_replace("/", "-", $nreintrag); // Zeichenumbennenung, falls "/" im der Band-Nummer vorkommt
                    
                $name = ($xml_mods->xpath('//mods:namePart')); // die Autoren zusammenstellen, bis max. 5
                $autoren = "kein Eintrag";
                if (isset($name[0])) {$autoren = $name[1]." ".substr($name[0], 0,1).".";}
                if (isset($name[2])) {$autoren = $name[1]." ".substr($name[0], 0,1).". & ".$name[3]." ".substr($name[2], 0,1).".";}
                if (isset($name[4])) {$autoren = $name[1]." ".substr($name[0], 0,1)."., ".$name[3]." ".substr($name[2], 0,1).". & ".$name[5]." ".substr($name[4], 0,1).".";}
                if (isset($name[6])) {$autoren = $name[1]." ".substr($name[0], 0,1)."., ".$name[3]." ".substr($name[2], 0,1)."., ".$name[5]." ".substr($name[4], 0,1).". & ".$name[5]." ".substr($name[6], 0,1).".";}
                if (isset($name[8])) {$autoren = $name[1]." ".substr($name[0], 0,1)."., ".$name[3]." ".substr($name[2], 0,1)."., ".$name[5]." ".substr($name[4], 0,1)."., ".$name[5]." ".substr($name[6], 0,1).". &".$name[7]." ".substr($name[8], 0,1).".";}

                // Datei umbennen und in neues Verzeichnis kopieren
                $neuer_dateiname = $dateiprefix."_".$jahreintrag."_".$nreintrag."_".$p1eintrag."-".$p2eintrag.".pdf"; // neuen Dateinamen definieren
                rename($aktdatei, $neuer_dateiname); // umbenennen und vom Quellordner zum Zielordner verschieben

                // Daten in Datenbank schreiben              
                echo $idnr ."<br>";
                $eintrag = "INSERT INTO mittflorsoz (year, nr, authors, title, p1, p2, text_lang, file, file_size, mitt_nr, authors_alt) VALUES ('$jahreintrag','$nreintrag','$autoren','$titeleintrag','$p1eintrag','$p2eintrag','$text_lang_dc_eintrag','$neuer_dateiname','$file_size','$idnr','$autoreneintrag2')";
                $eintragen = mysqli_query($db_link, $eintrag);
            }
        }

    closedir($handle);
    }
}
?>
