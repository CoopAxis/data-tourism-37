<?php

class TouraineloirevalleyHebergements extends Crawler
{

    protected $url = 'http://www.touraineloirevalley.com/dormir-manger-sortir/hebergements/(offset)/';

    protected function _crawl()
    {
        $this->log("\t", 'Crawling pages from "', $this->url, '"');

        $itemPerPage = 10;
        $itemOffset = 0;
        
        do {
            $gotSomeThing = false;
            $this->log("\t", 'Reading pages from ', ($itemOffset + 1), ' to ', ($itemOffset + $itemPerPage));
            
            $xhtml = $this->readPage($this->url . $itemOffset);
            
            if (! preg_match('#<div id="list-offres"\s*>(.*?)</div>#ms', $xhtml, $matches)) {
                throw new Exception('Not match "<div id="list-offres">...');
            }
            
            // echo 'got it'."\n".$matches[1]."\n";
            if (trim($matches[1]) != '') {
                try {
                    $this->getPages($xhtml, $itemOffset);
                } catch (Exception $ex) {
                    die($ex->getMessage());
                }
                $gotSomeThing = true;
                $itemOffset += $itemPerPage;
            }
        } while ($gotSomeThing);
        
        $this->log("\t", 'Done');
    }

    /**
     *
     * @param type $xhtml            
     * @param type $itemOffset            
     */
    function getPages($xhtml, $itemOffset)
    {
        $url = 'http://www.touraineloirevalley.com';
        
        $doc = new SimpleXMLElement($xhtml/*, LIBXML_ERR_FATAL|LIBXML_NOWARNING*/);
        $namespaces = $doc->getDocNamespaces();
        $doc->registerXPathNamespace('html', $namespaces['']);
        $nodes = $doc->xpath('//html:div[contains(concat(" ", @class, " "), " offre ")]');
        if ($nodes == null) {
            throw new Exception('NO MATCH FOUND: offre');
        }
        
        $itemIdx = 1;
        
        foreach ($nodes as $node) {
            $namespaces = $node->getDocNamespaces();
            $node->registerXPathNamespace('html', $namespaces['']);
            $n2s = $node->xpath('.//html:p[@class="view_link"]');
            if ($n2s == null) {
                throw new Exception('NO MATCH FOUND: view_link');
            }
            
            $xhtml2 = $this->readPage($url . $n2s[0]->a['href']);

            $name = str_replace('/dormir-manger-sortir/hebergements/', '', $n2s[0]->a['href']);
            $name = str_pad(($itemOffset + $itemIdx), 4, '0', STR_PAD_LEFT) . '.' . $name . '.html';
            $this->log("\t", $name);
            $this->storePage($name, $xhtml2);
            
            $itemIdx ++;
        }
    }
}
        