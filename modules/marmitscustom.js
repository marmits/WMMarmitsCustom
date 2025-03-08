/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * @author		Katie Horn <khorn@wikimedia.org>, Jeremy Postlethwaite <jpostlethwaite@wikimedia.org>
 */

( function ( $, mw ) {

/**
 * Find out when the article was last modified and insert it into the page.
 *
 * This is the primary function for this script.
 */
$( function () {
	LastModifiedExtension();
	setDateCreatedAndLasted();
} );

/**
 * Get the UTC Timestamp without microseconds
 *
 * @return {number}
 */
function getUtcTimeStamp () {
	return parseInt( new Date().getTime() / 1000 );
}

/**
 * Get the article history link
 *
 * @return {string} Return the article title
 */
function getArticleHistoryLink () {
	return mw.util.getUrl( mw.config.get( 'wgPageName' ), { action: 'history' } );
}

/**
 * Get the value from the meta tag: last-modified-timestamp
 *
 * @return {number}
 */
function getMetaLastModifiedTimestamp () {
	// Fetch the meta tag
	var metaTag = $( "meta[name=last-modified-timestamp]" );

	// If the tag was found, parse the value
	if ( metaTag.length ) {
		return parseInt( metaTag.attr( 'content' ) );
	}
	
	return 0;
}

/**
 * Get the modified text. This takes advantage of internationalization.
 *
 * @param {number} modifiedDifference The difference of time from now compared to last edited
 * @param {number} displayRange	The maximum unit of time to display for last updated
 *
 * displayRange
 * - 0: years	- display: years, months, days, hours, minutes, seconds  
 * - 1: months 	- display: months, days, hours, minutes, seconds  
 * - 2: days	- display: days, hours, minutes, seconds  
 * - 3: hours	- display: hours, minutes, seconds  
 * - 4: minutes	- display: minutes, seconds  
 * - 5: seconds	- display: seconds  
 *
 * @return {string}
 */
function getLastModifiedText ( modifiedDifference, displayRange ) {

	// Message to return
	var message = '';
	// The modifiedDifference should never be less than 0
	if ( modifiedDifference < 0 ) {
		modifiedDifference = 0;
	}
	var myLastEdit = modifiedDifference;
	
	if ( modifiedDifference < 60 ) {
		// seconds
		message = mw.msg( 'marmitscustom-seconds',  myLastEdit );
	} else if ( modifiedDifference < 3600 ) {
		// minutes
		if ( displayRange <= 4 ) {
			myLastEdit = parseInt( modifiedDifference / 60 );
			message = mw.msg( 'marmitscustom-minutes', myLastEdit );
		}
	} else if ( modifiedDifference < 86400 ) {
		// hours
		if ( displayRange <= 3 ) {
			myLastEdit = parseInt( modifiedDifference / 3600 );
			message = mw.msg( 'marmitscustom-hours', myLastEdit );
		}
	} else if ( modifiedDifference < 2592000 ) {
		// days
		if ( displayRange <= 2 ) {
			myLastEdit = parseInt( modifiedDifference / 86400 );
			message = mw.msg( 'marmitscustom-days', myLastEdit );
		}
	} else if ( modifiedDifference < 31536000 ) {
		// months
		if ( displayRange <= 1 ) {
			myLastEdit = parseInt( modifiedDifference / 2592000 );
			message = mw.msg( 'marmitscustom-months', myLastEdit );
		}
	} else {
		// years
		if ( displayRange === 0 ) {
			myLastEdit = parseInt( modifiedDifference / 31536000 );
			message = mw.msg( 'marmitscustom-years', myLastEdit );
		}		
	}
	
	return message;
}

/**
 * Get the value from the meta tag: last-modified-range
 *
 * @return {number}
 */
function getMetaRange () {
	// Fetch the meta tag
	var metaTag = $( 'meta[name=last-modified-range]' );

	// If the tag was found, parse the value
	if ( metaTag.length ) {
		return parseInt( metaTag.attr( 'content' ) );
	}
	
	return 0;
}

function LastModifiedExtension(){
	var metaTag = $( "meta[name=last-modified-range]" );
	if(getMetaRange() !== -1){
		var historyLink = getArticleHistoryLink(),
		html = '';

		html += '<div id="mwe-lastmodified">';
		html += '<a href="' + historyLink + '" title="' + mw.message( 'lastmodified-title-tag' ).escaped() + '">';
		html += getLastModifiedText( getUtcTimeStamp() - getMetaLastModifiedTimestamp(), getMetaRange() );
		html += '</a>';
		html += '</div>';

		// Insert the HTML into the web page, based on skin
		$( '.mw-indicators' ).append( html );
	}
}


// permet d'afficher dans le footer la date de la dernère mise à jour sur le wiki et la date du premiere article sur le wiki
// via l'api
// date de la dernère mise à jour avant #footer-places
// date de creation à la 1ere place de #footer-places
function setDateCreatedAndLasted() {

	//let urlwiki = $( "meta[name=urlwiki]" );
	let meta_date_created_wiki = $( "meta[http-equiv=date_created_wiki]" );
	let meta_date_lasted_wiki = $( "meta[http-equiv=date_lasted_wiki]" );
	let meta_title_lasted_wiki = $( "meta[http-equiv=title_lasted_wiki]" );

	if(meta_date_created_wiki.length && meta_date_lasted_wiki.length) {
		let mobile = $('#mw-mf-viewport');
		let footerplaces =  $( '#footer-places' );
		let footerplacesFirstChild = $( '#footer-places li:first-child' );
		let date_created_wiki = meta_date_created_wiki.attr( 'content' ) ;
		let date_lasted_wiki = meta_date_lasted_wiki.attr( 'content' ) ;
		let created = mw.msg( 'marmitscustom-wiki-creaded', date_created_wiki);
		let lasteupdated = mw.msg( 'marmitscustom-wiki-lastupdated', date_lasted_wiki);

		if(footerplaces.length) {
			let html = '';
			html += '<ul>';
			html += '<li>'+lasteupdated+' (page: '+meta_title_lasted_wiki.attr( 'content' ) +')</li>';
			html += '</ul>';
			footerplaces.before(html);
			html = '';
			let versionSite = 'marmitswikicreatedweb';
			if(mobile.length) {
				versionSite = 'marmitswikicreatedmobile';
			}
			html += '<li id="'+versionSite+'">' + created + '</li>';
			footerplacesFirstChild.before(html);
		}
	}
}


}( jQuery, mediaWiki ) ); 
