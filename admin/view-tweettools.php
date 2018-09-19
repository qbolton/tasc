<?php
  if (isset($this->form)) {
    //print_r($this->form->getFields());
    $form_values = $this->form->getFields();
  }
?>

<style type="text/css" scoped>
  label { font-weight: bold; }
  div.container { padding: 10px 0px 10px 0px; }
  table, th, td {
    border: 1px dashed black;
    border-collapse: collapse;
  }
  th, td {
    padding: 5px;
    text-align: left;
    vertical-align: top;
  }
  table#postlist {
    width: 100%;
  }
  .post_title {font-size:16px; font-weight:bold;}
</style>

<h2>SOCIAL MEDIA POST SELECTOR FOR THE ELOMBRE TOPICS NETWORK</h2>

<form name="tt_siteselect" action="" method="POST">
<label>Current Site Selected:</label>
<select name="tt_sitelist" id="sites">
<?php 
foreach($site_list as $site) {
  $selected = "";
  if (!$this->form->isSubmitted()) {
  if ( ($form_values['tt_sitelist']->value == $site->blog_id) || 
     ($form_values['tt_sitelist']->default_value == $site->blog_id)) {
     $selected = "selected";
  }
  }
  else {
    if ($site->blog_id == $blog_id) {
      $selected = "selected";
    }
  }
  print "<option name=\"option{$site->blog_id}\" value={$site->blog_id} {$selected}>{$site->path}</option>\n";
}
?>
</select>
</form>

<script type="text/javascript">
  jQuery(function() {
    jQuery('#sites').change(function() {
      this.form.submit();
    });
  });
</script>

<form name="tt_tweetselect" action="" method="POST">

<div class="container">

<table id="postlist">
<?php if (!empty($error_message)) { ?>
<tr><th colspan=5 style="color: red;"><?php print $error_message; ?></th></tr>
<?php } ?>
<tr>
<th>Select</th>
<th>Post Detail</th>
<th>Post Date</th>
<th>Post Score</th>
<th>Options/Links</th>
</tr>
<?php
$show_thumbnail = TRUE;
if (isset($this->post_list)) {
  foreach ($this->post_list as $post) {
    // don't show rows that already exist in the tweet table
    //if (array_key_exists($post->ID,$this->previously_tweeted)) { 
      // skip this
     // continue;
    //}

    $thumbnail = get_the_post_thumbnail_url($post->ID);
    if ($thumbnail === FALSE) {
      $show_thumbnail = FALSE; 
    }
    else {
      $show_thumbnail = TRUE;
    }

    // grab other meta pieces
    $post_meta = get_post_meta($post->ID); 
    $post_author = get_the_author_meta('display_name',$post->post_author);
    $post_author_url = get_the_author_meta('user_url',$post->post_author);
?>
<tr>
<td>
<input type="checkbox" name="tt_selected[]" value="<?php echo $blog_id.'|'.$post->ID; ?>">
</td>
<td width="55%">
<a class="post_title" href="<?php echo $post->guid; ?>" target="_blank"><strong><?php echo $post->post_title; ?></strong></a> 
<a href="<?php echo $post_author_url; ?>" target="_blank">[<?php echo $post_author; ?>]</a> 
<br/>
<?php echo $post->post_excerpt; ?>
</td>
<td width="8%">
<?php echo $post->post_date_gmt; ?>
</td>
<td width="">
<?php echo $post_meta['post_score'][0]; ?>
</td>
<td width="32%">
(<a href="<?php echo $post->meta_value; ?>" target="_blank">original post</a>)
<?php if ($show_thumbnail) { ?>
(<a href="<?php echo $thumbnail; ?>" target="_blank">see post image</a>)
<?php } ?>
</td>
</tr>

<?php
  }
}
?>
</table>
</div>

<input type="reset" name="form_reset" value="Clear Selections"/>
<input type="submit" name="form_submit" value="Submit Selections"/>

</form>
