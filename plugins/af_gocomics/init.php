<?php
class Af_GoComics extends \SmallSmallRSS\Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Strip unnecessary stuff from gocomics feeds",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		if (strpos($article["guid"], "gocomics.com") !== FALSE) {
			if (strpos($article["plugin_data"], "gocomics,$owner_uid:") === FALSE) {
				$doc = new DOMDocument();
				@$doc->loadHTML(\SmallSmallRSS\Fetcher::fetch($article["link"]));

				$basenode = false;

				if ($doc) {
					$xpath = new DOMXPath($doc);
					$entries = $xpath->query('(//img[@src])'); // we might also check for img[@class='strip'] I guess...

					$matches = array();

					foreach ($entries as $entry) {

						if (preg_match("/(http:\/\/assets.amuniversal.com\/.*)/i", $entry->getAttribute("src"), $matches)) {

							$entry->setAttribute("src", $matches[0]);
							$basenode = $entry;
							break;
						}
					}

					if ($basenode) {
						$article["content"] = $doc->saveXML($basenode);
						$article["plugin_data"] = "gocomics,$owner_uid:" . $article["plugin_data"];
					}
				}
			} elseif (isset($article["stored"]["content"])) {
				$article["content"] = $article["stored"]["content"];
			}
		}

		return $article;
	}

	function api_version() {
		return 2;
	}

}
?>
