<?php

class TouraineloirevalleyActivites extends Scrapper
{

    protected function _scrap()
    {

        $folder = $this->options[self::OPT_PAGES_DIR];

        $activites = array();
        $itemsCount = 1;
        
        $d = dir($folder);
        while (false !== ($entry = $d->read())) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }
            $this->log( "\t", $itemsCount , ' - ' , $entry );
            
            $xhtml = $this->readPage( $folder . DIRECTORY_SEPARATOR . $entry);
            // hack to work around php 5.0's problems with default namespaces and xpath()
            $xhtml = str_replace('xmlns=', 'ns=', $xhtml);
            
            $doc = new SimpleXMLElement($xhtml);
            $content = $doc->xpath('//div[contains(concat(" ", @class, " "), " content_block ")]');
            $h = $this->parsePage($content[0], $entry);
            // echo print_r($h,true);
            $h['filename'] = $entry;
            $activites[] = $h;
            $itemsCount ++;
        }
        $d->close();

        $filename = $this->options[self::OPT_DATA_DIR].DIRECTORY_SEPARATOR.$this->getName().'.csv' ;
        $csv = fopen($filename, 'w');

        $headers=array('nom','lat','lon','adr1','adr2','adr3','adr4','tel','mail','web', 'intro', 'visite', 'activites_nbr', 'activites', 'services_nbr', 'services', 'url');
        fputcsv($csv, $headers, ',','"');

        foreach( $activites as $h )
        {
            $r = array( $h['name'], $h['lat'], $h['lon'] );
            $r[] = isset($h['adr'][0]) ? $h['adr'][0] : '' ;
            $r[] = isset($h['adr'][1]) ? $h['adr'][1] : '' ;
            $r[] = isset($h['adr'][2]) ? $h['adr'][2] : '' ;
            $r[] = isset($h['adr'][3]) ? $h['adr'][3] : '' ;
            $r[] = isset($h['tel']) ? $h['tel'] : '' ;
            $r[] = isset($h['mail']) ? $h['mail'] : '' ;
            $r[] = isset($h['web']) ? $h['web'] : '' ;
            $r[] = isset($h['intro']) ? $h['intro'] : '' ; 
            $r[] = $h['visite']===true ? 'oui' : '' ;
            $r[] = count($h['activites']);
            $r[] = count($h['activites'])>0 ? implode( "\n", $h['activites']) : '' ;
            $r[] = count($h['services']);
            $r[] = count($h['services'])>0 ? implode( "\n", $h['services']) : '' ;
            $r[] = 'http://www.touraineloirevalley.com/dormir-manger-sortir/activites/'.preg_replace('/([0-9]{4}[\.])(.*)([\.]html)/', '${2}', $h['filename']);
        
            fputcsv($csv, $r, ',','"');
        }
        fclose($csv);

    }
    
    protected function parsePage(SimpleXMLElement $doc, $filename)
    {
        $nodes = $doc->xpath('//h1');
        
        if ($nodes == null)
            die('NO MATCH FOUND: h1');
        
        $h = array(
            'name' => null,
            'lat' => null,
            'lon' => null,
            'adr' => null,
            'visite' => null ,
            'activites' => null,
            'services' => null
        );
        
        $h['name'] = (string) $nodes[0];

        $nodes_block_offre = $doc->xpath('//div[contains(concat(" ", @class, " "), " block ") and contains(concat(" ", @class, " "), " offre ")]');

        $nodes = $nodes_block_offre[0]->xpath('//p[contains(concat(" ", @class, " "), " address ")]');
        if( $nodes != null )
        {
            $address = preg_split('/\n/m', (string) $nodes[0] );
            if( preg_match( '#LONGITUDE : ([0-9.]+) / LATITUDE : ([0-9.]+)#', $address[count($address)-1], $matches) )
            {
                $h['lat'] = $matches[2];
                $h['lon'] = $matches[1];
                array_pop($address);
            }
            $h['adr'] = $address;
        }

        $nodes = $nodes_block_offre[0]->xpath('//ul[contains(concat(" ", @class, " "), " coord ")]/li');
        if( $nodes != null )
        {
            foreach( $nodes as $li )
            {
                $n = $li->xpath('img[contains( @src, "picto_tel.png")]');
                if( $n != null )
                {
                    $h['tel'] = (string) $li->xpath('text()')[0] ;
                }
                else {
                    $n = $li->xpath('img[contains( @src, "picto_url.png")]');
                    if( $n != null )
                    {
                        $h['web'] = (string) $li->xpath('a')[0]['href'] ;
                    }
                    else{
                        $n = $li->xpath('a[starts-with( @href, "mailto:")]');
                        if( $n!=null)
                        {
                            $h['mail'] = substr( $n[0]['href'],7 );
                        }
                    }
                }
            }
        }

        //$nodes = $nodes_block_offre[0]->xpath('//ul[contains(concat(" ", @class, " "), " langues ")]/li');
        
        $nodes = $nodes_block_offre[0]->xpath('//div[contains(concat(" ", @class, " "), " intro ")]');
        if( $nodes != null )
        {
            $h['intro'] = (string) $nodes[0]->children()->asXML();
        }

        $nodes = $doc->xpath('//h2');
        foreach( $nodes as $node )
        {
            switch ( (string)$node )
            {
            	case 'Visites':
            	    $h['visite'] = true ;
            	    break;
        
            	case 'Activités':
            	    $activites = array();
            	    $n2s = $node->xpath('following-sibling::div[1]/ul/li');
            	    foreach($n2s as $n2)
            	    {
            	        $activites[] = (string) $n2 ;
            	    }
            	    $h['activites_nbr'] = count($activites);
            	    $h['activites'] = $activites;
            	    break;
        
            	case 'Services':
            	    $services = array();
            	    $n2s = $node->xpath('following-sibling::div[1]/ul/li');
            	    foreach($n2s as $n2)
            	    {
            	        $services[] = (string) $n2 ;
            	    }
            	    $h['services_nbr'] = count($services);
            	    $h['services'] = $services;
            	    break;
            }
        }
        
        return $h;
    }
    
}
