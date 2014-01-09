<?php 
$ROOT_DIR = str_replace('/mods/scp', '',str_replace('\\', '/', realpath(dirname(__FILE__)))).'/';
require_once($ROOT_DIR.'main.inc.php');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta http-equiv="cache-control" content="no-cache" />
    <meta http-equiv="pragma" content="no-cache" />
    <meta http-equiv="refresh" content="60">
    <title>osTicket :: Unallocated Tickets</title>
    <!--[if IE]>
    <style type="text/css">
        .tip_shadow { display:block !important; }
    </style>
    <![endif]-->
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-1.8.3.min.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-1.10.3.custom.min.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery.multifile.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor.min.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor-osticket.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor-fonts.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH ?>scp/js/bootstrap-typeahead.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH ?>scp/js/scp.js"></script>
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/thread.css" media="screen">
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/scp.css" media="screen">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/redactor.css" media="screen">
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/typeahead.css" media="screen">
    <link type="text/css" href="<?php echo ROOT_PATH; ?>css/ui-lightness/jquery-ui-1.10.3.custom.min.css"
         rel="stylesheet" media="screen" />
     <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome.min.css">
    <!--[if IE 7]>
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome-ie7.min.css">
    <![endif]-->
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/dropdown.css">
    <script type="text/javascript" src="<?php echo ROOT_PATH ?>scp/js/jquery.dropdown.js"></script>
    <style type="text/css">
        #container {
            width: 100%;
        }
        table.list caption { 
            font-size: 22px;
        }
        table.list thead th a {
            font-size: 22px;
        }
        table.list tbody td {
            font-size: 17px;
            vertical-align: middle;
            padding: 10px;
        }
        a.Icon { 
            font-size: 17px;
        }
    </style>
</head>
<body>
<div id="container">
    <div id="header">
        <div style="text-align: center; font-size: 30px; padding-top: 20px">Unallocated Tickets</div>
    </div>
    <div id="content">
<?php
//--------( Sorting options )------------------------------------------------
$sortOptions=array('date'=>'effective_date','ID'=>'ticketID',
    'pri'=>'priority_id','name'=>'user.name','subj'=>'subject',
    'status'=>'ticket.status','assignee'=>'assigned','staff'=>'staff',
    'dept'=>'dept_name');

$orderWays=array('DESC'=>'DESC','ASC'=>'ASC');

$queue = isset($_REQUEST['status'])?strtolower($_REQUEST['status']):$status;
$order_by=$order=null;
if($_REQUEST['sort'] && $sortOptions[$_REQUEST['sort']])
    $order_by =$sortOptions[$_REQUEST['sort']];
elseif($sortOptions[$_SESSION[$queue.'_tickets']['sort']]) {
    $_REQUEST['sort'] = $_SESSION[$queue.'_tickets']['sort'];
    $_REQUEST['order'] = $_SESSION[$queue.'_tickets']['order'];

    $order_by = $sortOptions[$_SESSION[$queue.'_tickets']['sort']];
    $order = $_SESSION[$queue.'_tickets']['order'];
}

if($_REQUEST['order'] && $orderWays[strtoupper($_REQUEST['order'])])
    $order=$orderWays[strtoupper($_REQUEST['order'])];

//--------( Save sort order for sticky sorting )-----------------
if($_REQUEST['sort'] && $queue) {
    $_SESSION[$queue.'_tickets']['sort'] = $_REQUEST['sort'];
    $_SESSION[$queue.'_tickets']['order'] = $_REQUEST['order'];
}

//--------( Set default sort by columns )-----------------
if(!$order_by ) {
    if($showanswered)
        $order_by='ticket.lastresponse, ticket.created'; //No priority sorting for answered tickets.
    elseif(!strcasecmp($status,'closed'))
        $order_by='ticket.closed, ticket.created'; //No priority sorting for closed tickets.
    elseif($showoverdue) //priority> duedate > age in ASC order.
        $order_by='priority_id, ISNULL(duedate) ASC, duedate ASC, effective_date ASC, ticket.created';
    else //XXX: Add due date here?? No -
        $order_by='priority_id, effective_date DESC, ticket.created';
}

$order=$order?$order:'DESC';
if($order_by && strpos($order_by,',') && $order)
    $order_by=preg_replace('/(?<!ASC|DESC),/', " $order,", $order_by);

$sort=$_REQUEST['sort']?strtolower($_REQUEST['sort']):'priority_id'; //Urgency is not on display table.
$x=$sort.'_sort';
$$x=' class="'.strtolower($order).'" ';

if($_GET['limit'])
    $qstr.='&limit='.urlencode($_GET['limit']);

//---------( MySQL Query to retrive data rows )-------------------------
$query = 'SELECT ticket.ticket_id,lock_id,ticketID,ticket.dept_id,ticket.staff_id,ticket.team_id  ,user.name ,email.address as email, dept_name  ,ticket.status,ticket.source,isoverdue,isanswered,ticket.created  ,IF(ticket.duedate IS NULL,IF(sla.id IS NULL, NULL, DATE_ADD(ticket.created, INTERVAL sla.grace_period HOUR)), ticket.duedate) as duedate  ,CAST(GREATEST(IFNULL(ticket.lastmessage, 0), IFNULL(ticket.reopened, 0), ticket.created) as datetime) as effective_date  ,CONCAT_WS(" ", staff.firstname, staff.lastname) as staff, team.name as team  ,IF(staff.staff_id IS NULL,team.name,CONCAT_WS(" ", staff.lastname, staff.firstname)) as assigned  ,IF(ptopic.topic_pid IS NULL, topic.topic, CONCAT_WS(" / ", ptopic.topic, topic.topic)) as helptopic  ,cdata.priority_id, cdata.subject '
        .'FROM '.TABLE_PREFIX.'ticket ticket ' 
        .'LEFT JOIN '.TABLE_PREFIX.'user user ON user.id = ticket.user_id '
        .'LEFT JOIN '.TABLE_PREFIX.'user_email email ON user.id = email.user_id '
        .'LEFT JOIN '.TABLE_PREFIX.'department dept ON ticket.dept_id=dept.dept_id '
        .'LEFT JOIN '.TABLE_PREFIX.'ticket_lock tlock ON (ticket.ticket_id=tlock.ticket_id AND tlock.expire>NOW()) '
        .'LEFT JOIN '.TABLE_PREFIX.'staff staff ON (ticket.staff_id=staff.staff_id) '
        .'LEFT JOIN '.TABLE_PREFIX.'team team ON (ticket.team_id=team.team_id) '
        .'LEFT JOIN '.TABLE_PREFIX.'sla sla ON (ticket.sla_id=sla.id AND sla.isactive=1) '
        .'LEFT JOIN '.TABLE_PREFIX.'help_topic topic ON (ticket.topic_id=topic.topic_id) ' 
        .'LEFT JOIN '.TABLE_PREFIX.'help_topic ptopic ON (ptopic.topic_id=topic.topic_pid) '  
        .'LEFT JOIN '.TABLE_PREFIX.'ticket__cdata cdata ON (cdata.ticket_id = ticket.ticket_id) '
        .'WHERE staff.staff_id IS NULL AND ticket.status = "open" '
        .'ORDER BY ' . $order_by . ' ' . $order;

//-------( Pagenate )-------------------------------------------
$total = db_num_rows(db_query($query));
$pagelimit=($_GET['limit'] && is_numeric($_GET['limit']))?$_GET['limit']:DEFAULT_PAGE_LIMIT;
$page=($_GET['p'] && is_numeric($_GET['p']))?$_GET['p']:1;
$pageNav=new Pagenate($total,$page,$pagelimit);
$pageNav->setURL('unallocatedJobs.php',$qstr.'&sort='.urlencode($_REQUEST['sort']).'&order='.urlencode($_REQUEST['order']));
$query .= ' LIMIT ' .$pageNav->getStart(). ',' . $pageNav->getLimit();

//------( Fetch priority information )--------------
$res = db_query('select * from '.PRIORITY_TABLE);
$prios = array();
while ($row = db_fetch_array($res))
    $prios[$row['priority_id']] = $row;

$hash = md5($query);
$_SESSION['search_'.$hash] = $query;
$res = db_query($query);
$showing=db_num_rows($res)?$pageNav->showing():"";

if(!$results_type)
    $results_type = ucfirst($status).' Tickets';

if($search)
    $results_type.= ' (Search Results)';

$negorder=$order=='DESC'?'ASC':'DESC'; //Negate the sorting..

//----------( Fetch the results )------------------
$results = array();
while ($row = db_fetch_array($res)) {
    $results[$row['ticket_id']] = $row;
}

//----------( Fetch attachment and thread entry counts )--------------------------
if ($results) {
    $counts_sql = 'SELECT ticket.ticket_id, count(attach.attach_id) as attachments,
        count(DISTINCT thread.id) as thread_count
        FROM '.TICKET_TABLE.' ticket
        LEFT JOIN '.TICKET_ATTACHMENT_TABLE.' attach ON (ticket.ticket_id=attach.ticket_id) '
     .' LEFT JOIN '.TICKET_THREAD_TABLE.' thread ON ( ticket.ticket_id=thread.ticket_id) '
     .' WHERE ticket.ticket_id IN ('.implode(',', db_input(array_keys($results))).')
        GROUP BY ticket.ticket_id';
    $ids_res = db_query($counts_sql);
    while ($row = db_fetch_array($ids_res)) {
        $results[$row['ticket_id']] += $row;
    }
}
?>
<form action="tickets.php" method="POST" name='tickets'>
<?php csrf_token(); ?>
 <input type="hidden" name="a" value="mass_process" >
 <input type="hidden" name="do" id="action" value="" >
 <input type="hidden" name="status" value="<?php echo Format::htmlchars($_REQUEST['status']); ?>" >
 <table class="list" border="0" cellspacing="1" cellpadding="2" width="100%">
    <caption><?php echo $showing; ?>&nbsp;&nbsp;&nbsp;<?php echo $results_type; ?></caption>
    <thead>
        <tr>
	        <th width="100">
                <a <?php echo $id_sort; ?> href="unallocatedJobs.php?sort=ID&order=<?php echo $negorder; ?><?php echo $qstr; ?>"
                    title="Sort By Ticket ID <?php echo $negorder; ?>">Ticket</a></th>
	        <th width="70">
                <a  <?php echo $date_sort; ?> href="unallocatedJobs.php?sort=date&order=<?php echo $negorder; ?><?php echo $qstr; ?>"
                    title="Sort By Date <?php echo $negorder; ?>">Date</a></th>
	        <th width="280">
                 <a <?php echo $subj_sort; ?> href="unallocatedJobs.php?sort=subj&order=<?php echo $negorder; ?><?php echo $qstr; ?>"
                    title="Sort By Subject <?php echo $negorder; ?>">Subject</a></th>
            <th width="170">
                <a <?php echo $name_sort; ?> href="unallocatedJobs.php?sort=name&order=<?php echo $negorder; ?><?php echo $qstr; ?>"
                     title="Sort By Name <?php echo $negorder; ?>">From</a></th>
            <?php
            if($search && !$status) { ?>
                <th width="60">
                    <a <?php echo $status_sort; ?> href="unallocatedJobs.php?sort=status&order=<?php echo $negorder; ?><?php echo $qstr; ?>"
                        title="Sort By Status <?php echo $negorder; ?>">Status</a></th>
            <?php
            } else { ?>
                <th width="120" <?php echo $pri_sort;?>>
                    <a <?php echo $pri_sort; ?> href="unallocatedJobs.php?sort=pri&order=<?php echo $negorder; ?><?php echo $qstr; ?>"
                        title="Sort By Priority <?php echo $negorder; ?>">Priority</a></th>
            <?php
            }

            if($showassigned ) {
                //Closed by
                if(!strcasecmp($status,'closed')) { ?>
                    <th width="150">
                        <a <?php echo $staff_sort; ?> href="unallocatedJobs.php?sort=staff&order=<?php echo $negorder; ?><?php echo $qstr; ?>"
                            title="Sort By Closing Staff Name <?php echo $negorder; ?>">Closed By</a></th>
                <?php
                } else { //assigned to ?>
                    <th width="150">
                        <a <?php echo $assignee_sort; ?> href="unallocatedJobs.php?sort=assignee&order=<?php echo $negorder; ?><?php echo $qstr; ?>"
                            title="Sort By Assignee <?php echo $negorder;?>">Assigned To</a></th>
                <?php
                }
            } else { ?>
                <th width="150">
                    <a <?php echo $dept_sort; ?> href="unallocatedJobs.php?sort=dept&order=<?php echo $negorder;?><?php echo $qstr; ?>"
                        title="Sort By Department <?php echo $negorder; ?>">Department</a></th>
            <?php
            } ?>
        </tr>
     </thead>
     <tbody>
        <?php
        $class = "row1";
        $total=0;
        if($res && ($num=count($results))):
            $ids=($errors && $_POST['tids'] && is_array($_POST['tids']))?$_POST['tids']:null;
            foreach ($results as $row) {
                $tag=$row['staff_id']?'assigned':'openticket';
                $flag=null;
                if($row['lock_id'])
                    $flag='locked';
                elseif($row['isoverdue'])
                    $flag='overdue';

                $lc='';
                if($showassigned) {
                    if($row['staff_id'])
                        $lc=sprintf('<span class="Icon staffAssigned">%s</span>',Format::truncate($row['staff'],40));
                    elseif($row['team_id'])
                        $lc=sprintf('<span class="Icon teamAssigned">%s</span>',Format::truncate($row['team'],40));
                    else
                        $lc=' ';
                }else{
                    $lc=Format::truncate($row['dept_name'],40);
                }
                $tid=$row['ticketID'];
                $subject = Format::htmlchars(Format::truncate($row['subject'],40));
                $threadcount=$row['thread_count'];
                if(!strcasecmp($row['status'],'open') && !$row['isanswered'] && !$row['lock_id']) {
                    $tid=sprintf('<b>%s</b>',$tid);
                }
                ?>
            <tr id="<?php echo $row['ticket_id']; ?>">
                <td align="center" title="<?php echo $row['email']; ?>" nowrap>
                  <div class="Icon <?php echo strtolower($row['source']); ?>Ticket ticketPreview"><?php echo $tid; ?></div></td>
                <td align="center" nowrap><?php echo Format::db_datetime($row['effective_date']); ?></td>
                <td><div <?php if($flag) { ?> class="Icon <?php echo $flag; ?>Ticket" title="<?php echo ucfirst($flag); ?> Ticket" <?php } ?>><?php echo $subject; ?>
                     &nbsp;
                     <?php echo ($threadcount>1)?" <small>($threadcount)</small>&nbsp;":''?>
                     <?php echo $row['attachments']?"<span class='Icon file'>&nbsp;</span>":''; ?>
                    </div>  
                </td>
                <td nowrap>&nbsp;<?php echo Format::truncate($row['name'],22,strpos($row['name'],'@')); ?>&nbsp;</td>
                <?php
                if($search && !$status){
                    $displaystatus=ucfirst($row['status']);
                    if(!strcasecmp($row['status'],'open'))
                        $displaystatus="<b>$displaystatus</b>";
                    echo "<td>$displaystatus</td>";
                } else { ?>
                <td class="nohover" align="center" style="background-color:<?php echo $prios[$row['priority_id']]['priority_color']; ?>;">
                    <?php echo $prios[$row['priority_id']]['priority_desc']; ?></td>
                <?php
                }
                ?>
                <td nowrap>&nbsp;<?php echo $lc; ?></td>
            </tr>
            <?php
            } //end of while.
        else: //not tickets found!! set fetch error.
            $ferror='There are no tickets here. (Leave a little early today).';
        endif; ?>
    </tbody>
    </table>
    <?php
    if($num>0){ //if we actually had any tickets returned.
        echo '<div style="font-size:17px">&nbsp;Page:'.$pageNav->getPageLinks().'&nbsp;</div>';
    } ?>
    </form>
    </div>
</div>
</body>
</html>
