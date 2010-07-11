<?php
function edit_contact(&$a,$contact_id) {

}

function contacts_post(&$a) {

	
	if(! local_user())
		return;

	$contact_id = intval($a->argv[1]);
	if(! $contact_id)
		return;
dbg(2);
print_r($_POST);
	$orig_record = q("SELECT * FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
		intval($contact_id),
		intval($_SESSION['uid'])
	);

	if(! count($orig_record)) {
		notice("Could not access contact record." . EOL);
		goaway($a->get_baseurl() . '/contacts');
		return; // NOTREACHED
	}

	$profile_id = intval($_POST['profile-assign']);
	if($profile_id) {
		$r = q("SELECT `id` FROM `profile` WHERE `id` = %d AND `uid` = %d LIMIT 1",
			intval($profile_id),
			intval($_SESSION['uid'])
		);
		if(! count($r)) {
			notice("Cannot locate selected profile." . EOL);
			return;
		}
	}
	$rating = intval($_POST['reputation']);
	if($rating > 5 || $rating < 0)
		$rating = 0;

	$reason = notags(trim($_POST['reason']));

	$r = q("UPDATE `contact` SET `profile-id` = %d, `rating` = %d, `reason` = '%s'
		WHERE `id` = %d AND `uid` = %d LIMIT 1",
		intval($profile_id),
		intval($rating),
		dbesc($reason),
		intval($contact_id),
		intval($_SESSION['uid'])
	);
	if($r)
		notice("Contact updated." . EOL);
	else
		notice("Failed to update contact record." . EOL);
	return;

}











function contacts_content(&$a) {

	if(! local_user()) {
		$_SESSION['sysmsg'] .= "Permission denied." . EOL;
		return;
	}



	if($a->argc == 3) {

		$contact_id = intval($a->argv[1]);
		if(! $contact_id)
			return;

		$cmd = $a->argv[2];

		$orig_record = q("SELECT * FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
			intval($contact_id),
			intval($_SESSION['uid'])
		);

		if(! count($orig_record)) {
			notice("Could not access contact record." . EOL);
			goaway($a->get_baseurl() . '/contacts');
			return; // NOTREACHED
		}


		$photo = str_replace('-4.jpg', '' , $r[0]['photo']);
		$photos = q("SELECT `id` FROM `photo` WHERE `resource-id` = '%s' AND `uid` = %d",
				dbesc($photo),
				intval($_SESSION['uid'])
		);
	
		if($cmd == 'block') {
			$blocked = (($orig_record[0]['blocked']) ? 0 : 1);
			$r = q("UPDATE `contact` SET `blocked` = %d WHERE `id` = %d AND `uid` = %d LIMIT 1",
					intval($blocked),
					intval($contact_id),
					intval($_SESSION['uid'])
			);
			if($r) {
				$msg = "Contact has been " . (($blocked) ? '' : 'un') . "blocked." . EOL ;
				notice($msg);
			}
			goaway($a->get_baseurl() ."/contacts/$contact_id");
			return; // NOTREACHED
		}

		if($cmd == 'drop') {
			$r = q("DELETE FROM `contact` WHERE `id` = %d AND `uid` = %d LIMIT 1",
				intval($contact_id),
				intval($_SESSION['uid']));
			if(count($photos)) {
				foreach($photos as $p) {
					q("DELETE FROM `photos` WHERE `id` = %d LIMIT 1",
						$p['id']);
				}
			}
			if($intval($contact_id))
				q("DELETE FROM `item` WHERE `contact-id` = %d LIMIT 1",
					intval($contact_id)
				);
	
			notice("Contact has been removed." . EOL );
			goaway($a->get_baseurl() . '/contacts');
			return; // NOTREACHED
		}
	}

	if(($a->argc == 2) && intval($a->argv[1])) {

		$contact_id = intval($a->argv[1]);
		$r = q("SELECT * FROM `contact` WHERE `uid` = %d and `id` = %d LIMIT 1",
			$_SESSION['uid'],
			intval($contact_id)
		);
		if(! count($r)) {
			notice("Contact not found.");
			return;
		}

		require_once('view/contact_selectors.php');

		$tpl = file_get_contents("view/contact_edit.tpl");

		$direction = '';
		if(strlen($r[0]['issued-id'])) {
			if(strlen($r[0]['dfrn-id'])) {
				$direction = DIRECTION_BOTH;
				$dir_icon = 'images/lrarrow.gif';
				$alt_text = 'Mutual Friendship';
			}
			else {
				$direction = DIRECTION_IN;
				$dir_icon = 'images/larrow.gif';
				$alt_text = 'is a fan of yours';
			}
		}
		else {
			$direction = DIRECTION_OUT;
			$dir_icon = 'images/rarrow.gif';
			$alt_text = 'you are a fan of';
		}

		$o .= replace_macros($tpl,array(
			'$profile_select' => contact_profile_assign($r[0]['profile-id']),
			'$contact_id' => $r[0]['id'],
			'$block_text' => (($r[0]['blocked']) ? 'Unblock this contact' : 'Block this contact' ),
			'$blocked' => (($r[0]['blocked']) ? '<div id="block-message">Currently blocked</div>' : ''),
			'$rating' => contact_reputation($r[0]['rating']),
			'$reason' => $r[0]['reason'],
			'$groups' => '', // group_selector(),
			'$photo' => $r[0]['photo'],
			'$name' => $r[0]['name'],
			'$dir_icon' => $dir_icon,
			'$alt_text' => $alt_text

		));

		return $o;

	}

	if(($a->argc == 2) && ($a->argv[1] == 'all'))
		$sql_extra = '';
	else
		$sql_extra = " AND `blocked` = 0 ";

	$tpl = file_get_contents("view/contacts-top.tpl");
	$o .= replace_macros($tpl,array(
		'$hide_url' => ((strlen($sql_extra)) ? 'contacts/all' : 'contacts' ),
		'$hide_text' => ((strlen($sql_extra)) ? 'Show Blocked Connections' : 'Hide Blocked Connections')
	)); 

	switch($sort_type) {
		case DIRECTION_BOTH :
			$sql_extra = " AND `dfrn-id` != '' AND `issued-id` != '' ";
			break;
		case DIRECTION_IN :
			$sql_extra = " AND `dfrn-id` = '' AND `issued-id` != '' ";
			break;
		case DIRECTION_OUT :
			$sql_extra = " AND `dfrn-id` != '' AND `issued-id` = '' ";
			break;
		case DIRECTION_ANY :
		default:
			$sql_extra = '';
			break;
	}

	$r = q("SELECT * FROM `contact` WHERE `uid` = %d $sql_extra",
		intval($_SESSION['uid']));

	if(count($r)) {

		$tpl = file_get_contents("view/contact_template.tpl");

		foreach($r as $rr) {
			if($rr['self'])
				continue;
			$direction = '';
			if(strlen($rr['issued-id'])) {
				if(strlen($rr['dfrn-id'])) {
					$direction = DIRECTION_BOTH;
					$dir_icon = 'images/lrarrow.gif';
					$alt_text = 'Mutual Friendship';
				}
				else {
					$direction = DIRECTION_IN;
					$dir_icon = 'images/larrow.gif';
					$alt_text = 'is a fan of yours';
				}
			}
			else {
				$direction = DIRECTION_OUT;
				$dir_icon = 'images/rarrow.gif';
				$alt_text = 'you are a fan of';
			}

			$o .= replace_macros($tpl, array(
				'$id' => $rr['id'],
				'$alt_text' => $alt_text,
				'$dir_icon' => $dir_icon,
				'$thumb' => $rr['thumb'], 
				'$name' => $rr['name'],
				'$url' => (($direction != DIRECTION_IN) ? "redir/{$rr['id']}" : $rr['url'] )
			));
		}
	}
	return $o;
}