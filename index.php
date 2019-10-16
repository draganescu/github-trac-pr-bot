<?php

// used to view GH webhook ping contents
//file_put_contents('github-log'.time().'.txt', $_REQUEST, FILE_APPEND);
//exit;
 
require 'includes/class-trac.php';
require 'includes/class-IXR.php';
require 'includes/credentials.php';

function is_inline_comment( $gh_json ) {
	return $gh_json->comment && $gh_json->comment->pull_request_review_id;
}

function is_pr_comment( $gh_json ) {
	return $gh_json->comment && ! $gh_json->comment->pull_request_review_id && $gh_json->comment->id;
}

function is_cr_comment( $gh_json ) {
	return $gh_json->action === 'submitted';
}

function is_edit( $gh_json ) {
	return $gh_json->action === 'edited';
}

function is_create( $gh_json ) {
	return $gh_json->action === 'created';
}

function is_new_trac_comment( $ticket, $comment_identifier ) {
	$trac_comment = get_trac_comment( $ticket, $comment_identifier );
	if( $trac_comment === false ) {
		return true;
	};
	return false;
}

function get_trac_comment( $ticket, $comment_identifier ) {
	global $trac_xmlrpc;
	$ticket_comments = $trac_xmlrpc->ticket_get_comments( $ticket );
	foreach( $ticket_comments as $ticket_comment ) {		
		$ticket_comment_content = str_replace("\n", "", $ticket_comment[4]);
		if ( empty( strval($comment_identifier) ) ) {
			continue;
		}
		if( strpos( $ticket_comment_content, strval($comment_identifier) ) !== false ) {
			return $ticket_comment;
		}
	};
	return false;
}

function edit_trac_comment( $comment, $from = false, $to = false ) {
	$comment_content = $comment[4];
	$comment_content = str_replace(
		$from,
		$to,
		$comment_content
	);
	$comment[4] = $comment_content;
	return $comment;
}

function create_trac_pr_comment( $id, $user_link, $user_name, $created_at, $comment, $comment_link ) {

	$comment_template="{{{#!comment
%s
}}}

[%s %s] commented on %s:

%s

[%s View on GitHub]";
	
	$comment = sprintf(
		$comment_template,
		$id,
		$user_link,
		$user_name,
		$created_at,
		$comment,
		$comment_link
	);
	return $comment;
}

function create_trac_review_comment( $identifier, $user_link, $user_name, $created_at, $cr_comment, $inline_comment, $comment_link ) {

	$comment_template="{{{#!comment
%s
}}}

[%s %s] reviewed on %s:

{{{#!comment
CRCOMMENT
}}}
%s
{{{#!comment
/CRCOMMENT
}}}

{{{#!comment
INLINECOMMENT
}}}
%s
{{{#!comment
/INLINECOMMENT
}}}

[%s View on GitHub]";

	$comment = sprintf(
		$comment_template,
		$identifier,
		$user_link,
		$user_name,
		$created_at,
		$cr_comment,
		$inline_comment,
		$comment_link
	);
	return $comment;

}

function append_trac_cr_comment( $comment, $new_cr_comment ) {
	$comment_content .= "{{{#!comment
CRCOMMENT
}}}
".$new_cr_comment."
{{{#!comment
/CRCOMMENT
}}}";
	$comment[4] .= $comment_content;
	return $comment;
}

function append_trac_inline_comment( $comment, $new_inline_comment ) {
	$comment_content = $comment[4];
	$comment_content = str_replace(
"{{{#!comment
/INLINECOMMENT
}}}",
"\n" . $new_inline_comment . "\n" .
"{{#!comment
/INLINECOMMENT
}}}",
		$comment_content
	);
	$comment[4] = $comment_content;
	return $comment;
}

$gh_json = json_decode( $_POST['payload'] );

//file_put_contents('./gh-log-test'.time().'-'.md5($_POST['payload']).'.txt', $_REQUEST, FILE_APPEND); exit;

//$gh_json = json_decode(file_get_contents('./gh-log-test-review-submitted.txt'));

$trac_xmlrpc = new \Trac( 'pullrequestbot', PRBOT_WPORG_PASSWORD, "core.trac.wordpress.org", '/login/xmlrpc', 443, true );

if ( is_pr_comment( $gh_json ) ) {
	$ticker_identifier = $gh_json->issue->number;
}

if ( is_inline_comment( $gh_json ) || is_cr_comment( $gh_json ) ) {
	$ticker_identifier = $gh_json->pull_request->number;
}

$ticket = $trac_xmlrpc->ticket_query( "prnumber=".$ticker_identifier )[0];

?>
Is pr comment: <?=is_pr_comment($gh_json); ?> <br/>
Is cr comment: <?=is_cr_comment($gh_json); ?> <br/>
Is inline comment: <?=is_inline_comment($gh_json); ?> <br/>
Has identifier: <?=$ticker_identifier; ?> <br/>
Is create: <?=is_create($gh_json); ?> <br/>
Is edit: <?=is_edit($gh_json); ?> <br/>
Review id: <?=$gh_json->review->id; ?> <br/>
Is new comment by review id <?=$gh_json->review->id?>: <?=is_new_trac_comment( $ticket, $gh_json->review->id ); ?> <br/>
Comment by review id: <?=print_r(get_trac_comment( $ticket, $gh_json->review->id ));?> <br />
Is new comment by pull request id <?=$gh_json->comment->pull_request_review_id?>: <?=is_new_trac_comment( $ticket, $gh_json->comment->pull_request_review_id ); ?> <br/>
Comment by pull request id: <?=print_r(get_trac_comment( $ticket, $gh_json->comment->pull_request_review_id ));?> <br />
<?php

// $ticket = $trac_xmlrpc->ticket_query( "prnumber=100")[0];
// $comment = get_trac_comment( $ticket, '538803654' );
// echo '<pre>';
// var_dump($comment);
// exit;

if ( is_pr_comment( $gh_json ) ) {
	$comment = create_trac_pr_comment(
		$gh_json->comment->id,
		$gh_json->comment->user->html_url,
		$gh_json->comment->user->login,
		$gh_json->comment->created_at,
		$gh_json->comment->body,
		$gh_json->comment->html_url,
	);
}

/* Disabled comment updating
if ( is_edit( $gh_json ) && ( is_pr_comment( $gh_json ) ) ) {
	$comment = get_trac_comment( $ticket, $gh_json->comment->id );
	$comment = edit_trac_pr_comment(
		$comment,
		$gh_json->changes->body->from,
		$gh_json->comment->body
	);
}


if ( is_edit( $gh_json ) && ( is_inline_comment( $gh_json ) || is_cr_comment( $gh_json ) ) ) {
	$comment = get_trac_comment( $ticket, $gh_json->comment->pull_request_review_id );
	$comment = edit_trac_comment(
		$comment,
		$gh_json->changes->body->from,
		$gh_json->comment->body
	);
}
*/

if ( is_inline_comment( $gh_json ) ) {
	$comment = create_trac_review_comment(
		$gh_json->comment->pull_request_review_id,
		$gh_json->comment->user->html_url,
		$gh_json->comment->user->login,
		$gh_json->comment->created_at,
		'',
		$gh_json->comment->body,
		$gh_json->comment->html_url
	);
}

/* Disabled comment updating
if ( is_inline_comment( $gh_json ) && ! is_new_trac_comment( $ticket, $gh_json->comment->pull_request_review_id ) ) {
	$comment = get_trac_comment( $ticket, $gh_json->comment->pull_request_review_id );
	$comment = append_trac_inline_comment(
		$comment,
		$new_inline_comment
	);
}
*/


if ( is_cr_comment( $gh_json ) ) {
	if( ! empty( $gh_json->review->body ) ) {
		$comment = create_trac_review_comment(
			$gh_json->review->id,
			$gh_json->review->user->html_url,
			$gh_json->review->user->login,
			$gh_json->review->submitted_at,
			$gh_json->review->body,
			'',
			$gh_json->review->url
		);
	};
}

/* Disabled comment updating
if ( is_cr_comment( $gh_json ) && ! is_new_trac_comment( $ticket, $gh_json->review->id ) ) {
	$comment = get_trac_comment( $ticket, $gh_json->review->id );
	$comment = append_trac_cr_comment(
		$comment,
		$gh_json->review->body
	);
}
*/

// we save the edited comment
$trac_xmlrpc->ticket_update( $ticket, $comment );



