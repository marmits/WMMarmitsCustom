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
     * @throws MWException
     */
    //Permet d'authoriser l'accès à l'api seulement pour un utlisateur enregistrer
    //Permet d'authoriser l'accès à une liste de ressource définie pour les anonymes
    public static function onAPIAfterExecute(ApiBase $module ): bool
    {
        $read_data = false;
        $urls_authorized=[
            '/w/api.php?action=query&list=logevents&lelimit=1&ledir=older&format=json',
            '/w/api.php?action=query&list=logevents&lelimit=1&ledir=newer&format=json'
        ];

        $url_request = $module->getRequest()->getRequestURL();
        if(!in_array($url_request, $urls_authorized) ) {

            if($module->getUser()->isRegistered()){
                $read_data = true;
            }
        } else {
            $read_data = true;
        }
        if($read_data === false){
            $module->dieWithException(new HttpError(401, 'Ressource interdite'));
        }

		return true;
	}

	/**
	 * Function provenant de l'extension LastModified	 
	 * @param OutputPage &$out
	 * @param Skin &$sk
	 * @return bool
	 */
	public static function onLastModified( &$out, &$sk ) {
		global $wgMarmitsCustomRange;

		// paramètre MarmitsCustomRange de l'extension à -1 => désactive le rendu
		if($wgMarmitsCustomRange !== -1){

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
				$out->addModules( 'marmits.custom' );
			}
		}

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
