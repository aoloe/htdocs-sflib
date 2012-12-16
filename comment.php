<?php
/**
Tiny commenting sytem
- don't allow any formatting but accept links to external resources.
- show it as pseudo <pre> (have a look at how fluxbb does it.
- show links to images as images + link below
TODO:
- finish the ip-based filter
- allow editor+ to edit/hide comments (does it really need editing?)
- allow the calling application to set variable that will be added in the links and the form (page_id)
- add an optional (and external?) captcha
*/
define('COMMENT_PATH_DATA', 'data/comment/');
define('COMMENT_USER_RIGHTS_USER', 1);
define('COMMENT_USER_RIGHTS_EDITOR', 2);
define('COMMENT_USER_RIGHTS_ADMIN', 3);

function debug($label, $value = null) {
    echo("<pre>$label".(isset($value) ? ' :'.print_r($value, 1) : '')."</pre>\n");
}

class Comment {

    protected $uri = null;
    public function set_uri($uri) {$this->uri = $uri;} // the uri attached to the comments list

    protected $open = true;
    public function set_open($open) {$this->open = $open;} // is it still possible to add comments?

    protected $user = null;
    public function set_user($user) {$this->user = $user;}
    protected $user_rights = 0;
    public function set_user_rights($user_rights) {$this->user_rights = $user_rights;}

    protected $request_prefix = "";
    public function set_request_prefix($prefix) {$this->request_prefix = $prefix;}

    protected $css_prefix = ""; // TODO: use one or more main css ids instead?
    public function set_css_prefix($prefix) {$this->css_prefix = $prefix;}

    protected $comment = null;
    protected $comment_last_id = 0;

    protected $checking_ip_spammer = true;
    public function set_cecking_ip_spammer($check = true) {$this->checking_ip_spammer = $check;}

    protected $showing_title = false;
    public function set_showing_title($show = true) {$this->showing_title = $show;}

    protected $error = array();
    protected $message = array();

    protected function read() {
        $result = false;
        if (is_null($this->comment)) {
            if (is_null($this->uri)) {
                $this->error[] = 'no reference URI has been defined.';
            } else if (file_exists(COMMENT_PATH_DATA.$this->uri)) {
                $this->comment = array();
                $id_max = 0;
                $fp = fopen(COMMENT_PATH_DATA.$this->uri, 'r');
                while ($item = fgetcsv($fp)) {
                    // debug('item', $item);
                    $key = $item[0];
                    $this->comment[$key] = array (
                        'id' => $item[0],
                        'title' => $item[1],
                        'comment' => $item[2],
                        'datetime' => $item[3],
                        'user' => $item[4], // only if loged in
                        'author' => $item[5], // only if not logged in
                        'notify' => $item[6], // also a user setting "notify where i've commented"
                        'author_email' => $item[7], // only if notify and not loged in
                        'hidden' =>  $item[8], // hidden until moderated
                        'moderation_key' => $item[9], // when empty, it has been moderated
                        'author_ip' => $item[10],
                    );
                    $id_max = max($id_max, $item[0]);
                }
                $this->comment_last_id = $id_max;
                fclose($fp);
                $result = true;
            } else if (is_writable(COMMENT_PATH_DATA)) {
                $this->comment = array();
                $this->write();
            } else {
                $this->error[] = 'could not read the comments file.';
            }
        } // if !$this->comment
        return $result;
    } // Comment::read()

    protected function write() {
        $fp = fopen(COMMENT_PATH_DATA.$this->uri, 'w');
        foreach ($this->comment as $key => $value) {
            // Debugger::structure('value', $value);
            fputcsv($fp, $value);
        }
        fclose($fp);
    } //  Comment::write()

    public function process($request = null) {
        $this->read();
        if (is_null($request)) {
            $request = array();
            $field = array();
            // debug('_REQUEST', $_REQUEST);
            if (array_key_exists($this->request_prefix.'add', $_REQUEST)) {
                $field = array('add', 'title', 'author', 'author_email', 'notify', 'comment');
            } elseif (array_key_exists($this->request_prefix.'delete', $_REQUEST)) {
                $field = array('delete', 'id');
            } elseif (array_key_exists($this->request_prefix.'hide', $_REQUEST)) {
                $field = array('hide', 'id');
            } elseif (array_key_exists($this->request_prefix.'moderate', $_REQUEST)) {
                $field = array('moderate', 'moderation_key', 'moderation_action');
            }
            foreach ($field as $item) {
                $request[$item] = (array_key_exists($this->request_prefix.$item, $_REQUEST) ? $_REQUEST[$item] : '');
            }
        }
        if (array_key_exists('add', $request)) {
            $this->add(
                 $request['title'],
                 $request['author'],
                 $request['author_email'],
                 $request['notify'],
                 $request['comment']
            );
        } elseif (array_key_exists('moderate', $request)) {
            $this->moderate(
                 $request['moderation_key'],
                 $request['moderation_action'] == 'accept'
            );
        }
    } // Comment::process()

    protected function is_ip_spammer($ip) {
        $result = false;
        // TODO: check $_SERVER['REMOTE_ADDR'] against a ip spam checker (is this a function of comment?)
        return $result;
    } // Comment::is_ip_spammer()

    protected function add($title, $author, $author_email, $notify, $comment) {
        $this->comment_last_id = $this->comment_last_id + 1;
        $author_ip = $_SERVER['REMOTE_ADDR'];
        if ($comment != "" && (!$this->checking_ip_spammer || !$this->is_ip_spammer($author_ip))) {
            $comment = array (
                'id' => $this->comment_last_id,
                'title' => $comment,
                'comment' => $comment,
                'datetime' => date('Y-m-d H:i:s'),
                'user' => isset($this->user) ? $this->user : '',
                'author' => isset($this->user) ? '' : $author,
                'notify' => $notify, // default taken from user
                'author_email' => $author_email, // default taken from user
                'hidden' => isset($this->user) ? 0 : 1,
                'moderation_key' => isset($this->user) ? '' : md5(uniqid()),
                'author_ip' => $author_ip,
            );
            $this->comment[$comment['id']] = $comment;
            $this->write();
            $this->comment[$comment['id']]['hidden'] = false; // always show it once for the user having submitted it
            if ($this->user_rights == 0) {
                $this->message[] = 'Thank you for your comment. It will be published after having been reviewed';
            }
            // TODO: send an email to the moderator and ask to accept the comment
        } // if comment
    } // Comment::add()

    /**
     * anybody having the key generated by add() can moderate
     */
    public function moderate($moderation_key, $accept = true) {
        // TODO: is comment a class parameter?
        // debug('moderation_key', $moderation_key);
        // debug('accept', $accept);
        foreach ($this->comment as $key => $value) {
            if ($value['moderation_key'] == $moderation_key) {
                // debug('accept', $accept);
                // debug('key', $key);
                // debug('value', $value);
                if ($accept) {
                    $this->comment[$key]['moderation_key'] = '';
                    $this->comment[$key]['hidden'] = false;
                } else {
                    unset($this->comment[$key]);
                }
                $this->write();
            }
        }
    } // Comment::moderate()

    public function delete($id) {
        if (($this->user_rights > COMMENT_USER_RIGHTS_EDITOR) && array_key_exists($id, $this->comment)) {
            unset($this->comment[$id]);
        }
    } // Comment::delete()

    public function get_rendering($template = null) {
        $result = "";
        foreach ($this->message as $item) {
            $result .= '<p>'.$item.'</p>';
        }
        if (count($this->error) > 0) {
            foreach ($this->error as $item) {
                $result .= '<p>'.$item.'</p>';
            }
        } else {
            // debug('comment', $this->comment);
            if ($this->open) {
                $result .= template(
                    COMMENT_TEMPLATE_FORM_COMMENT,
                    array(
                        'showing_title' => $this->showing_title,
                        'showing_author' => is_null($this->user),
                    )
                );
            }
            foreach (array_reverse($this->comment) as $key => $value) {
                // debug('value', $value);
                if (!$value['hidden'] || ($this->user_rights >= COMMENT_USER_RIGHTS_EDITOR)) {
                    $result .= template(
                        COMMENT_TEMPLATE_COMMENT,
                        array(
                            'showing_title' => $this->showing_title,
                            'showing_moderation' => (($this->user_rights >= COMMENT_USER_RIGHTS_EDITOR) && ($value['moderation_key'] != "")),
                            'showing_editor' => ($this->user_rights >= COMMENT_USER_RIGHTS_EDITOR),
                            'showing_admin' => ($this->user_rights == COMMENT_USER_RIGHTS_ADMIN),
                            'title' => $value['title'],
                            'author' => $value['author'],
                            'author_email' => $value['author_email'],
                            'date' => $value['datetime'],
                            'comment' => $value['comment'],
                            'hidden' => $value['hidden'],
                            'moderation_key' => $value['moderation_key'],
                        )
                    );
                }
            }
        }
        return $result;
    } // Comment::get_rendering()

} // Comment

//TODO: set the request prefix
$template_string =  <<<EOT
<form action="" method="post">
{{if::showing_title=1::
<p><label for= "title">Title:</label><input type="text" name="title" id="title" /></p>
::endif}}
<p><label for= "comment">Comment:</label><textarea name="comment" id="comment"></textarea></p>
{{if::showing_author=1::
<p><label for= "author">Author:</label><input type="text" name="author" id="author" /></p>
<p><label for= "author_email">E-Mail:</label><input type="text" name="author_email" id="author_email" /></p>
::endif}}
<p><label for= "notify">Notify:</label><input type="checkbox" name="notify" id="notify" /></p>
<p><input type="submit" name="add" value="Send" />
</form>
EOT;
define('COMMENT_TEMPLATE_FORM_COMMENT', $template_string);

//TODO: set the css prefix
$template_string =  <<<EOT
<div class="comment">
{{if::showing_title=1::
<h3 class="title">{{title}}</h3>
::endif}}
<p>{{author}} on {{date}}</p>
{{if::showing_admin=1::
<p>{{author_email}}</p>
::endif}}
<p class="comment">{{comment}}</p>
{{if::showing_moderation=1::
<form method="post" action="?">
<input type="submit" name="moderate" value="Accept" />
<input type="hidden" name="moderation_action" value="accept" />
<input type="hidden" name="moderation_key" value="{{moderation_key}}" />
</form>
<form method="post" action="?">
<input type="submit" name="moderate" value="Delete" />
<input type="hidden" name="moderation_action" value="delete" />
<input type="hidden" name="moderation_key" value="{{moderation_key}}" />
</form>
::endif}}
{{if::showing_editor=1::
<form method="post" action="?">
<input type="submit" name="admin" value="Switch visibility" />
<input type="hidden" name="admin_action" value="visibility" />
<input type="hidden" name="id" value="{{id}}" />
</form>
<form method="post" action="?">
<input type="submit" name="admin" value="Delete" />
<input type="hidden" name="admin_action" value="delete" />
<input type="hidden" name="id" value="{{id}}" />
</form>
::endif}}
</div>
EOT;
define('COMMENT_TEMPLATE_COMMENT', $template_string);

// a very simple template engine, with if and foreach
function template($template, $parameter = null) {
    $result = '';
    $pattern = '/\{{(foreach|if)::(.*?)::(.*?)::(endforeach|endif)}}/s';
    $result = preg_replace_callback(
        $pattern,
        function ($match)  use ($parameter) {
            // echo("<pre>".print_r($match,1)."</pre>");
            $result = '';
            if ($match[1] == 'foreach') {
                foreach ($parameter[$match[2]] as $item) {
                    foreach ($item as $key => $value) {
                        unset($item[$key]);
                        $item['{{'.$key.'}}'] = $value;
                    }
                    $result .= strtr($match[3], $item);
                    // $result .= preg_replace('/{{*.?}}/', array_keys($value[$match[2]]), array_values($value[$match[2]]));
                }
            } elseif ($match[1] == 'if') {
                // allowed operators: = ! < > none
                // debug('parameter', $parameter);
                // debug('match', $match);
                // debug('match', $match);
                preg_match("/(.+?)([=!<>])(.*)/", $match[2], $match_if);
                // debug('match_if', $match_if);
                $ok_if = false;
                switch ($match_if[2]) {
                    case "=";
                        $ok_if = ($parameter[$match_if[1]] == $match_if[3]);
                    break;
                    case "!";
                        $ok_if = ($parameter[$match_if[1]] != $match_if[3]);
                    break;
                    case "<";
                        $ok_if = ($parameter[$match_if[1]] < $match_if[3]);
                    break;
                    case ">";
                        $ok_if = ($parameter[$match_if[1]] > $match_if[3]);
                    break;
                }
                // debug('ok_if', $ok_if);
                if ($ok_if) {
                    $result = $match[3];
                }
                
            }
            // debug('result', $result);
            return $result;
        },
        $template
    );
    foreach ($parameter as $key => $value) {
        if (is_array($value)) {
            unset($parameter[$key]);
        } else {
            unset($parameter[$key]);
            $parameter['{{'.$key.'}}'] = $value;
        } 
    }
    $result = strtr($result, $parameter);
    // $result = preg_replace('/{{*.?}}/', );
    return $result;
} // template
