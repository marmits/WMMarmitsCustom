<?php
/* 
* fork =>
* https://www.mediawiki.org/wiki/Extension:LastModified
*/

/**
 *
 */
class MarmitsCustomHooks {

	
    /**
     * @return string
     */
    private static function getUrlBase(): string
    {
        return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'];
    }
    /**
     * @return string[]
     */
    private static function getUrlAuthorized(): array
    {
        return [            
			'/w/api.php?action=query&list=logevents&lelimit=1&ledir=newer&format=json',	
			'/w/api.php?action=query&list=recentchanges&formatversion=2&rclimit=1&format=json',					
			'/w/api.php'
        ];
    }
    /**
     * @throws MWException
     */
    //1- Permet d'authoriser l'accès à l'api seulement pour un utlisateur enregistré
    //2- Permet d'authoriser l'accès à l'api pour les anonymes à une liste de ressources définies
    public static function onAPIAfterExecute(ApiBase $module ): bool
    {
        $read_data = false;
        $url_request = $module->getRequest()->getRequestURL();
	
        if(!in_array($url_request, self::getUrlAuthorized()) ) {
            if($module->getUser()->isRegistered()){
                $read_data = true;
            }
        } else {
            $read_data = true;
        }
        if($read_data === false){
            $module->dieWithException(new HttpError(401, 'Sorry! Forbidden ressource => blocked by '.MarmitsCustomHooks::class.' Extension '));
        }
		return true;
	}

    /**
     * Function provenant de l'extension LastModified
     * @param OutputPage &$out
     * @param Skin &$sk
     * @return bool
     * @throws DateMalformedStringException
     */
	public static function onLastModified( &$out, &$sk ) {
		global $wgMarmitsCustomRange;
        global $wgMarmitsCustomInfoDate;

		// paramètre MarmitsCustomRange de l'extension à -1 => désactive le rendu
		//if($wgMarmitsCustomRange !== -1){

			$context = $out->getContext();
			$title = $context->getTitle();

			// Don't try to proceed if we don't care about the target page
			if ( !( $title instanceof Title && $title->getNamespace() == 0 && $title->exists() ) ) {
				return true;
			}

			$article = Article::newFromTitle( $title, $context );

			if ( $article ) {
				$timestamp = wfTimestamp( TS_UNIX, $article->getPage()->getTimestamp() );
				$out->addMeta( 'http:last-modified', date( 'r', $timestamp ) );
				$out->addMeta( 'last-modified-timestamp', $timestamp );
				$out->addMeta( 'last-modified-range', $wgMarmitsCustomRange );

			}
		//}

        // Permet d'exploiter l'api pour récupérer des données et les ajouter dans les metas de la page
        // et pouvoir les exploiter via javascript
        if($wgMarmitsCustomInfoDate === 1) {
            $out->addMeta( 'http:urlwiki', self::getUrlBase()  );
            $jsonOlder = file_get_contents(self::getUrlBase().self::getUrlAuthorized()[0]);
            $jsonNewer = file_get_contents(self::getUrlBase().self::getUrlAuthorized()[1]);
			
            $objOlder = json_decode($jsonOlder, true);
            $objNewer = json_decode($jsonNewer, true);		
			
            $firstcreate = new DateTimeImmutable($objOlder['query']['logevents'][0]['timestamp']);
            $lastcreate = new DateTimeImmutable($objNewer['query']['recentchanges'][0]['timestamp']);
            $date_lastcreate = $lastcreate->add(new DateInterval('PT1H'))->format('d/m/Y à H:i');
            $date_firstcreate = $firstcreate->format('d/m/Y');

            $out->addMeta( 'http:date_created_wiki', $date_firstcreate  );
            $out->addMeta( 'http:date_lasted_wiki', $date_lastcreate  );
        }
        $out->addModules( 'marmits.custom' );
		return true;
	}


	/*
	Protège l'accès à certaines pages
	*/
	public static function Confidentiel( ){

		global $wgRequest;
		global $mediaWiki;
		global $wgPage;
		global $wgOut;	
		$private = false;


		$context = RequestContext::getMain();
		$wgUser = $context->getUser();
		
		if($wgUser->isSafeToLoad() === true){
			
			if ( $wgUser->isNamed() === false ) {

				$page = $wgRequest->getText( 'title' );


				$pageConnexion = "Spécial:Connexion";
				$pageDeConnexion = "Spécial:Déconnexion";
				$pageCat = "Spécial:Catégories";
				$pagePage = "Spécial:Toutes_les_pages_*";
				$pageRecheche = "Spécial:Recherche";
				$pagePrivateCategory = "Catégorie:Private";

				

				if (in_array("Private", $wgOut->getCategories())) {
					$private = true;
				}

				if(
					str_contains($page,'Spécial') || 
					str_contains($page,'MediaWiki') || 
					str_contains($page,'Catégorie:Private') 
				) {
					$private = true;

					if(
					str_contains($page,'Spécial:Connexion') || 
					str_contains($page,'Spécial:Déconnexion') || 
					str_contains($page,'Spécial:Recherche')
					) {
						$private = false;
					}
				}

				//  var_dump($private);
			}

			if($private === true){
				header('Location: ' . $pageConnexion);
				exit();
			}
			
		}
		
		return true;
	}


	/*
	* Custom le footer
	*/
	public static function onSkinAddFooterLinks( Skin $skin, string $key, array &$footerlinks ) { 

		/*
		$json = file_get_contents('https://marmits.com/w/api.php?action=query&list=logevents&lelimit=1&ledir=newer&format=json');
		$obj = json_decode($json, true);
		$firstcreate = new DateTimeImmutable($obj['query']['logevents'][0]['timestamp']);
		$date_firstcreate = $firstcreate->format('Y-m-d');
		
		
		if ( $key === 'places' ) { 
			$footerlinks['privacy'] = $skin->msg( 'toto' )->parse();
		}
		if ( $key === 'info' ) { 
			$footerlinks['create'] = Html::rawElement( 'a', 
				[ 
					'href' => 'https://my.url/',
					'rel' => 'noreferrer noopener' // not required, but recommended for security reasons
				], 
				$date_firstcreate
			); 
		}
		*/
		return true;
	}

	/*
	* Supprime le lien discussion de la page
	* Supprime le lien voir source de la page
	*/
	public static function onPagelinks ( $skinTemplate, &$links ) {

		$context = RequestContext::getMain();
		$wgUser = $context->getUser();
		
		// supprime le lien discussion de la page	
		 unset( $links['associated-pages']['talk'] );
		 unset( $links['views']['viewsource'] );
		if($wgUser->isSafeToLoad() === true){
			if ( $wgUser->isNamed() === false ) {
				unset( $links['views']['history'] );
			}
		}
	
		 return true;	
	}


	/*
	* Protège l'accès à l'information de la page
	*/
	public static function onInfoPage ( $context, &$pageInfo ) {

		$context = RequestContext::getMain();
		$wgUser = $context->getUser();
		$pageConnexion = "index.php?title=Spécial:Connexion";
		 if($wgUser->isSafeToLoad() === true){
			   
			if ( $wgUser->isNamed() === false ) {
				header('Location: ' . $pageConnexion);
				exit();
			}
		}
		return true;
	}


}
