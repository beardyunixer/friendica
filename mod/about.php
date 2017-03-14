<?php

function about_init(App $a) {

	if(! x($a->page,'aside'))
		$a->page['aside'] = '';

	if($a->argc > 1)
		$which = htmlspecialchars($a->argv[1]);
	else {
		logger('profile error: mod_about ' . $a->query_string, LOGGER_DEBUG);
			notice( t('Requested about page is not available.') . EOL );
			$a->error = 404;
			return;
		}

	$profile = 0;

	profile_load($a,$which,$profile);
}


function about_content(App $a, $update = 0) {
	require_once('include/bbcode.php');

//	Temporarily abandoned...
//	if (! has_permission('view_profile',$owner,$observer))
//		return login();

	if (get_config('system','block_public') && (! local_user()) && (! remote_user())) {
		return login();
	}

	$o = bbcode(get_pconfig($a->profile_uid,'about_page','content'));

	if (! get_pconfig($a->profile_uid,'about_page','disable_advanced_profile')) {
	$o .= advanced_profile($a);
		call_hooks('profile_advanced',$o);
	}

	return $o;

}
