<?php
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\MediaWikiServices;



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
			'/w/api.php?action=query&list=logevents&formatversion=2&lelimit=1&ledir=newer&format=json',	
			'/w/api.php?action=query&list=recentchanges&formatversion=2&formatversion=2&rclimit=1&format=json',					
			'/w/api.php'
        ];
    }
	

	/**
	 * Function to log failed login attempts with IP address
	 * @param AuthenticationResponse $response
	 * @param User $user
	 * @param string $username
	 * @param array $extraData
	 * @return bool
	 */
    public static function onAuthManagerLoginAuthenticateAudit( $response, $user, $username, $extraData ) {
		
        $request = RequestContext::getMain()->getRequest();
        $ip = $request->getIP();
		global $wgMarmitsCustomPathLogFileFailed;

        // Log failed login attempts with IP address
        if ( $response && $response->status !== AuthenticationResponse::PASS ) {
            $logMessage = sprintf(
                "Login failed for user '%s' from IP '%s'\n",
                $username,
                $ip
            );
            file_put_contents($wgMarmitsCustomPathLogFileFailed, $logMessage, FILE_APPEND );
        }
        return true;
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
           
			$lastcreate = new DateTime( $objNewer['query']['recentchanges'][0]['timestamp']);
			$lastcreate->setTimezone( new DateTimeZone( date_default_timezone_get() ) );

            $out->addMeta( 'http:date_created_wiki', $firstcreate->format('d/m/Y')  );
            $out->addMeta( 'http:date_lasted_wiki', $lastcreate->format('d/m/Y à H:i'));
            $out->addMeta( 'http:title_lasted_wiki', $objNewer['query']['recentchanges'][0]['title']);
        }
        $out->addModules( 'marmits.custom' );
		return true;
	}


	/** 
	 * Protège l'accès à certaines pages
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

	/**
     * Initialise les namespaces Private au chargement de l'extension
     * Hook: SetupAfterCache
     */
    public static function initNamespaces(): void {
        if (!defined('NS_PRIVATE')) {
            define('NS_PRIVATE', 3000);
            define('NS_PRIVATE_TALK', 3001);

            global $wgExtraNamespaces, $wgNamespaceProtection;
            $wgExtraNamespaces[NS_PRIVATE] = 'Private';
            $wgExtraNamespaces[NS_PRIVATE_TALK] = 'Private_talk';

            // Edition réservée aux sysop
            $wgNamespaceProtection[NS_PRIVATE] = ['sysop'];
        }
    }

    /**
     * Bloque l’indexation des pages Private
     * Hook: SearchDataForIndex
     */
    public static function onSearchDataForIndex(Title $title, &$text, &$terms, &$weights): bool {
        return !self::isPrivatePage($title);
    }

    /**
     * Protège l’accès aux pages Private pour les utilisateurs non-admins
     * Hook: BeforeInitialize
     */
    public static function onBeforeInitialize(&$title, &$unused, &$output, &$user, $request, $mediaWiki): bool {
        if (!$title instanceof Title || $user->isAllowed('protect')) {
            return true;
        }

        if ($title->inNamespace(NS_PRIVATE) || self::isPrivatePage($title)) {
            $output->setStatusCode(403);
            $output->showErrorPage('permissionserrors', 'badaccess');
            return false;
        }

        return true;
    }

    /**
     * Filtre les résultats de recherche pour les non-admins
     * Hook: ShowSearchHit
     */
    public static function onShowSearchHit($searchPage, $result, $terms, &$link, &$redirect, &$section, &$extract, &$score, &$size, &$date, &$related, &$html): bool {
        $user = $searchPage->getUser();
        if ($user->isAllowed('protect')) {
            return true;
        }

        $title = $result->getTitle();
        if ($title->inNamespace(NS_PRIVATE) || self::isPrivatePage($title)) {
            $link = '';
            $html = '';
            return false;
        }

        return true;
    }

    /**
     * Vérifie si une page appartient à la catégorie Private
     */
    private static function isPrivatePage(Title $title): bool {
        if (!$title->exists() || $title->isSpecialPage()) {
            return false;
        }

        $page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle($title);
        if (!$page || !$page->exists()) {
            return false;
        }

        foreach ($page->getCategories() as $category) {
            if ($category->getDBkey() === 'Private') {
                return true;
            }
        }

        return false;
    }
		 

	
}
