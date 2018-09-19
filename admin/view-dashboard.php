<?php
?>

<style type="text/css" scoped>
  label { font-weight: bold; }
  div.container { width: 98%; padding: 10px 0px 10px 0px; }
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
  .box_title {
    text-align: center;
    font-size:24px; 
    font-weight:bold; 
    background-color: #cccccc;
    padding: 15px 10px 15px 10px;
  }

  .running {
    background-color: #66AC6F;
  }

  .nextrun {
    background-color: #ffffcc;
  }

  .alreadyrun {
    background-color: #ffffff;
  }
</style>

<div class="container">
<div class="box_title">RECENT JOB STATUS REPORT</div>
<table id="postlist">
<tr>
<th>Job Name</th>
<th>Run Date</th>
<th>Run Status</th>
</tr>
<?php
if (isset($job_stats)) {
  $row_count = 0;
  $css_classes = "";
  foreach ($job_stats as $job) {
    $css_classes = "alreadyrun";
    if ($row_count >= count($job_stats)-4) { $css_classes = "nextrun"; }
    if (strcasecmp($job->job_exec_status,"RUNNING") == 0) { $css_classes = "running"; }
?>
<tr class="<?php echo $css_classes; ?>">
<td width="55%">
<strong><?php echo $job->job_name; ?></strong>
<br />
<?php echo $job->job_desc; ?>
</td>
<td width="8%">
<?php echo $job->job_exec_date; ?>
</td>
<td width="">
<?php echo $job->job_exec_status; ?>
</td>
</tr>

<?php
    $row_count++;
  }
}
?>
</table>
</div>
