<?php
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\MediaWikiServices;
use RecentChange;


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
     * Règles d'accès autorisées
     * Chaque élément contient :
     *   - 'path' : chemin de l'URL
     *   - 'params' : tableau clé => valeur des paramètres obligatoires
     *   - 'registered_only' : booléen, si vrai seuls les utilisateurs enregistrés y ont accès
     */
    private static function getAccessRules(): array {
        return [
			[
				'path' => '/w/api.php',
				'params' => [
				'action' => 'logout',
				],
				'registered_only' => false, // autorise tout le monde
			],
            // accès complet à ces URLs statiques
            [
                'path' => '/w/api.php',
                'params' => [
                    'action' => 'query',
                    'list' => 'logevents',
                    'formatversion' => '2',
                    'lelimit' => '1',
                    'ledir' => 'newer',
                    'format' => 'json'
                ],
                'registered_only' => false,
            ],
            [
                'path' => '/w/api.php',
                'params' => [
                    'action' => 'query',
                    'list' => 'recentchanges',
                    'formatversion' => '2',
                    'rclimit' => '1',
                    'format' => 'json'
                ],
                'registered_only' => false,
            ],
            // exemple dynamique feedrecentchanges RSS
            [
                'path' => '/w/api.php',
                'params' => [
                    'action' => 'feedrecentchanges',
                    'feedformat' => 'rss',
                ],
                'registered_only' => false,
            ],
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
        $request_path = parse_url($url_request, PHP_URL_PATH);
        $params = $module->getRequest()->getValues();

        foreach (self::getAccessRules() as $rule) {
            // Vérifie le chemin
            if ($request_path !== $rule['path']) {
                continue;
            }

            // Vérifie que tous les paramètres obligatoires correspondent
            $match = true;
            foreach ($rule['params'] as $key => $value) {
                if (!isset($params[$key]) || (string)$params[$key] !== (string)$value) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                // si la règle exige utilisateur enregistré
                if ($rule['registered_only'] && !$module->getUser()->isRegistered()) {
                    continue;
                }
                $read_data = true;
                break;
            }
        }

        // accès complet aux utilisateurs enregistrés (si aucune règle spécifique ne match)
        if (!$read_data && $module->getUser()->isRegistered()) {
            $read_data = true;
        }

        if (!$read_data) {
            $module->dieWithException(
                new HttpError(401, 'Sorry! Forbidden resource => blocked by ' . MarmitsCustomHooks::class . ' Extension')
            );
        }

        return true;
    }


    public static function onLastModified( &$out, &$sk ) {
		global $wgMarmitsCustomRange;
		global $wgMarmitsCustomInfoDate;

        $title = $out->getTitle();


		$context = $out->getContext();


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

		// Exploiter l'API pour récupérer des données
		if ( $wgMarmitsCustomInfoDate === 1 ) {
			$out->addMeta( 'http:urlwiki', self::getUrlBase() );

			// On récupère dynamiquement les URLs autorisées pour 'query' (logevents et recentchanges)
			$rules = self::getAccessRules();

			$jsonOlder = $jsonNewer = null;

			foreach ( $rules as $rule ) {
				if ( isset($rule['params']['action']) && $rule['params']['action'] === 'query' ) {
					if ( isset($rule['params']['list']) && $rule['params']['list'] === 'logevents' ) {
						$url = self::getUrlBase() . $rule['path'] . '?' . http_build_query($rule['params']);
						$jsonOlder = file_get_contents($url);
					} elseif ( isset($rule['params']['list']) && $rule['params']['list'] === 'recentchanges' ) {
						$url = self::getUrlBase() . $rule['path'] . '?' . http_build_query($rule['params']);
						$jsonNewer = file_get_contents($url);
					}
				}
			}

			if ($jsonOlder && $jsonNewer) {
				$objOlder = json_decode($jsonOlder, true);
				$objNewer = json_decode($jsonNewer, true);

				$firstcreate = new DateTimeImmutable($objOlder['query']['logevents'][0]['timestamp']);
				$lastcreate = new DateTime($objNewer['query']['recentchanges'][0]['timestamp']);
				$lastcreate->setTimezone(new DateTimeZone(date_default_timezone_get()));

				$out->addMeta('http:date_created_wiki', $firstcreate->format('d/m/Y'));
				$out->addMeta('http:date_lasted_wiki', $lastcreate->format('d/m/Y à H:i'));
				$out->addMeta('http:title_lasted_wiki', $objNewer['query']['recentchanges'][0]['title']);
			}
		}

		$out->addModules('marmits.custom');

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
     * Filtrer les RC pour masquer les pages privées aux anonymes
     * Hook: ChangesListSpecialPageQuery
     *
     * @param string $name            Nom de la special page (Recentchanges, Recentchangeslinked, Watchlist)
     * @param array &$tables          Tables
     * @param array &$fields          Colonnes
     * @param array &$conds           WHERE
     * @param array &$query_options   Options
     * @param array &$join_conds      JOINs
     * @param FormOptions $opts       Options de formulaire
     * @return bool
     */
    public static function onChangesListSpecialPageQuery(
        $name, &$tables, &$fields, &$conds, &$query_options, &$join_conds, $opts
    ): bool {
        
        $user = RequestContext::getMain()->getUser();

        // On ne filtre que pour les non connectés
        if ( $user->isRegistered() ) {
            return true;
        }

        // (optionnel) exclure le namespace "Private" si défini
        if ( defined('NS_PRIVATE') ) {
            $conds[] = 'rc_namespace != ' . NS_PRIVATE;
        }

        // Exclure les pages appartenant à [[Category:Private]]
        // On joint categorylinks sur rc_cur_id et on garde uniquement les lignes
        // où il n'y a PAS de correspondance (cl_from IS NULL).
        $dbr = MediaWikiServices::getInstance()
            ->getDBLoadBalancer()->getConnection( DB_REPLICA );

        $tables[] = 'categorylinks';
        $join_conds['categorylinks'] = [
            'LEFT JOIN',
            // Attention: utiliser une valeur quotée pour cl_to
            'categorylinks.cl_from = rc_cur_id AND categorylinks.cl_to = ' . $dbr->addQuotes( 'Private' )
        ];
        $conds[] = 'categorylinks.cl_from IS NULL';

        // Masque aussi la page de catégorie elle-même : Catégorie:Private
        $conds[] = 'NOT (rc_namespace = ' . NS_CATEGORY .
        ' AND rc_title = ' . $dbr->addQuotes('Private') . ')';


        return true;
    }

    /**
     * Filtrer Spécial:Nouvelles_pages côté SQL pour masquer les pages privées.
     * Hook: SpecialNewpagesConditions
     *
     * @param NewPagesPager $pager
     * @param FormOptions $opts
     * @param array &$conds
     * @param array &$tables
     * @param array &$fields
     * @param array &$join_conds
     * @return bool
     */
    public static function onSpecialNewpagesConditions(
        $pager, $opts, &$conds, &$tables, &$fields, &$join_conds
    ): bool {
        $user = RequestContext::getMain()->getUser();

        // Laisse tout visible aux utilisateurs autorisés (ex: possédant 'protect', comme dans ton code)
        if ( $user->isAllowed( 'protect' ) ) {
            return true;
        }

        // (Optionnel) exclure tout le namespace Private si tu veux une double barrière
        if ( defined( 'NS_PRIVATE' ) ) {
            $conds[] = 'page_namespace != ' . NS_PRIVATE;
        }

        // Exclure les pages catégorisées [[Category:Private]]
        // NewPagesPager joint déjà 'page' via 'page_id = rc_cur_id' (cf. source),
        // on peut donc joindre categorylinks sur page_id et filtrer.
        $dbr = MediaWiki\MediaWikiServices::getInstance()
            ->getDBLoadBalancer()->getConnection( DB_REPLICA );

        $tables[] = 'categorylinks';
        $join_conds['categorylinks'] = [
            'LEFT JOIN',
            'categorylinks.cl_from = page_id AND categorylinks.cl_to = ' . $dbr->addQuotes( 'Private' )
        ];
        // On ne garde que les lignes sans correspondance catégorie 'Private'
        $conds[] = 'categorylinks.cl_from IS NULL';

        return true;
    }

    /**
     * Vérifie si une page appartient à la catégorie Private
     */
    public static function isPrivatePage(Title $title): bool {
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
