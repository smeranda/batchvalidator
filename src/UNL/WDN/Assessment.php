<?php
class UNL_WDN_Assessment
{
    public $baseUri;
    
    public static $htmlValidatorURI = "http://validator.unl.edu/check";
    
    public $db;
    
    function __construct($baseUri, $db)
    {
        $this->baseUri = $baseUri;
        $this->db      = $db;
    }
    
    /**
     * 
     * @return Spider
     */
    protected function getSpider($loggers = array(), $filters = array())
    {
        $downloader       = new Spider_Downloader();    
        $parser           = new Spider_Parser();
        $spider           = new Spider($downloader, $parser);
        
        foreach ($loggers as $logger) {
            $spider->addLogger($logger);
        }

        foreach ($filters as $filter) {
            $spider->addUriFilter($filter);
        }
        
        //Add default filters
        $spider->addUriFilter('Spider_AnchorFilter');
        $spider->addUriFilter('Spider_MailtoFilter');
        $spider->addUriFilter('UNL_WDN_Assessment_FileExtensionFilter');
        
        return $spider;
    }
    
    function checkInvalid()
    {
        $vlogger = new UNL_WDN_Assessment_ValidateInvalidLogger($this);
        
        $spider  = $this->getSpider(array($vlogger));
        
        $spider->spider($this->baseUri);
    }

    /**
     * Will recheck all metrics for every page
     * (save results to DB)
     */
    function check()
    {
        $this->removeEntries();

        $uriLogger = new UNL_WDN_Assessment_URILogger($this);
        $validationLogger = new UNL_WDN_Assessment_HTMLValidationLogger($this);
        $templateHTMLLogger = new UNL_WDN_Assessment_TemplateHTMLLogger($this);
        $templateDEPLogger = new UNL_WDN_Assessment_TemplateDEPLogger($this);
        $linkChecker = new UNL_WDN_Assessment_LinkChecker($this);

        $spider  = $this->getSpider(array($uriLogger, $validationLogger, $templateHTMLLogger, $templateDEPLogger, $linkChecker));

        $spider->spider($this->baseUri);
    }
    
    function removeEntries()
    {
        //Remove assessment entries
        $sth = $this->db->prepare('DELETE FROM assessment WHERE baseurl = ?');
        $sth->execute(array($this->baseUri));
        
        //remove url_has_badlinks entries
        $sth = $this->db->prepare('DELETE FROM url_has_badlinks WHERE baseurl = ?');
        $sth->execute(array($this->baseUri));
    }
    
    function getSubPages()
    {
        $sth = $this->db->prepare('SELECT * FROM assessment WHERE baseurl = ?;');
        $sth->execute(array($this->baseUri));
        return $sth->fetchAll();
    }
    
    function getBadLinksForPage($url)
    {
        $sth = $this->db->prepare('SELECT * FROM url_has_badlinks WHERE url = ?;');
        $sth->execute(array($url));
        return $sth->fetchAll();
    }
    
    function pageWasValid($uri)
    {
        if ($this->getValidityStatus($uri) == '0') {
            return true;
        }
        return false;
    }
    
    function getValidityStatus($uri)
    {
        $sth = $this->db->prepare('SELECT html_errors FROM assessment WHERE baseurl = ? AND url = ?;');
        $sth->execute(array($this->baseUri, $uri));
        $result = $sth->fetch();
        return $result['html_errors'];
    }

    function getTitle()
    {
        $page = @file_get_contents($this->baseUri);
        
        if (strlen($page)) {
            $results = array();
            
            preg_match("/\<title\>(.*)\<\/title\>/", $page, $results);
            
            if (isset($results[1])) {
                return $results[1];
            }
        }
        
        return "unknown";
    }
    
    function getLastScanDate()
    {
        $sth = $this->db->prepare('SELECT MAX(timestamp) as scan_date FROM assessment WHERE baseurl = ?');
        $sth->execute(array($this->baseUri));
        $result = $sth->fetch();
        
        if (isset($result['scan_date'])) {
            return $result['scan_date'];
        }
        
        return false;
    }
    
    public static function getCurrentTemplateVersions()
    {
        if (!$json = file_get_contents(dirname(__FILE__) . "/../../../tmp/templateversions.json")) {
            throw new Exception("tmp/templateversions.json does not exist.  Please run scripts/getLatestTemplateVersions.php");
        }
        
        return json_decode($json, true);
    }
    
    function getJSONstats()
    {
        $versions = self::getCurrentTemplateVersions();
        
        $stats = array();
        $stats['site_title'] = $this->getTitle();
        $stats['last_scan'] = $this->getLastScanDate();
        $stats['total_pages'] = 0;
        $stats['total_html_errors'] = 0;
        $stats['total_bad_links'] = 0;
        $stats['total_current_template_html'] = 0;
        $stats['total_current_template_dep'] = 0;
        $stats['current_template_html'] = $versions['html'];
        $stats['current_template_dep'] = $versions['dep'];
        
        $stats['pages'] = array();
        
        $i = 0;
        foreach ($this->getSubPages() as $page) {
            $stats['pages'][$i]['page'] = $page['url'];
            
            $stats['pages'][$i]['html_errors'] = $page['html_errors'];
            
            if ($page['valid'] != 'unknown') {
                $stats['total_html_errors'] += $page['html_errors'];
            }
            
            $stats['pages'][$i]['template_html']['version'] = $page['template_html'];
            $stats['pages'][$i]['template_html']['current'] = false;
            
            if ($page['template_html'] != 'unknown' && $page['template_html'] == $versions['html']) {
                $stats['total_current_template_html']++;
                $stats['pages'][$i]['template_html']['current'] = true;
            }
            
            $stats['pages'][$i]['template_dep']['version'] = $page['template_dep'];
            $stats['pages'][$i]['template_dep']['current'] = false;

            if ($page['template_dep'] != 'unknown' && $page['template_dep'] == $versions['dep']) {
                $stats['total_current_template_dep']++;
                $stats['pages'][$i]['template_dep']['current'] = true;
            }
            
            $stats['pages'][$i]['bad_links'] = array();
            
            foreach ($this->getBadLinksForPage($page['url']) as $link) {
                $stats['pages'][$i]['bad_links'][$link['code']][] = $link['link_url'];

                $stats['total_bad_links']++;
            }
            
            $i++;
        }

        $stats['total_pages'] = $i;
        
        return json_encode($stats);
    }
}