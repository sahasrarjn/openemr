<?php
// +-----------------------------------------------------------------------------+
// Copyright (C) 2011 ZMG LLC <sam@zhservices.com>
//
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
//
// A copy of the GNU General Public License is included along with this program:
// openemr/interface/login/GnuGPL.html
// For more information write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
//
// Author:   Eldho Chacko <eldho@zhservices.com>
//           Vinish K <vinish@zhservices.com>
//
// +------------------------------------------------------------------------------+
//SANITIZE ALL ESCAPES
$sanitize_all_escapes=true;
//

//STOP FAKE REGISTER GLOBALS
$fake_register_globals=false;
//
require('globals.php');
require('eRx_xml.php');
$userRole=sqlQuery("select * from users where username=?",array($_SESSION['authUser']));
$userRole['newcrop_user_role'] = preg_replace('/erx/','',$userRole['newcrop_user_role']);
$msg='';
$doc = new DOMDocument();
$doc->formatOutput = true;
$GLOBALS['total_count']=60;
$r = $doc->createElement( "NCScript" );
$r->setAttribute('xmlns','http://secure.newcropaccounts.com/interfaceV7');
$r->setAttribute('xmlns:NCStandard','http://secure.newcropaccounts.com/interfaceV7:NCStandard');
$r->setAttribute('xmlns:xsi','http://www.w3.org/2001/XMLSchema-instance');
$doc->appendChild( $r );

credentials($doc,$r);
user_role($doc,$r);
$page=$_REQUEST['page'];
destination($doc,$r,$page,$pid);
account($doc,$r);
if($userRole['newcrop_user_role']!='manager')
{
    location($doc,$r);
}
if($userRole['newcrop_user_role']=='doctor' || $page=='renewal')
{
    LicensedPrescriber($doc,$r);
}
if($userRole['newcrop_user_role']=='manager' || $userRole['newcrop_user_role']=='admin' || $userRole['newcrop_user_role']=='nurse')
{
    Staff($doc,$r);
}
if($userRole['newcrop_user_role']=='supervisingDoctor')
{
    SupervisingDoctor($doc,$r);
}
if($userRole['newcrop_user_role']=='midlevelPrescriber')
{
    MidlevelPrescriber($doc,$r);
}
$prescIds='';
if($pid)
{
    Patient($doc,$r,$pid);
    $active = '';
    if($GLOBALS['erx_upload_active']==1)
        $active = 'and active=1';    
    $res_presc=sqlStatement("select id from prescriptions where patient_id=? and erx_source='0' and erx_uploaded='0' $active limit 0,".$GLOBALS['total_count'],array($pid));
    $presc_limit=sqlNumRows($res_presc);
    $med_limit=$GLOBALS['total_count']-$presc_limit;
    while($row_presc=sqlFetchArray($res_presc))
    {
        $prescIds.=$row_presc['id'].":";
    }
    $prescIds=preg_replace('/:$/','',$prescIds);
    if($_REQUEST['id'] || $prescIds)
    {
        if($_REQUEST['id'])
        $prescArr=explode(':',$_REQUEST['id']);
        elseif($prescIds)
        $prescArr=explode(':',$prescIds);
        foreach($prescArr as $prescid)
        {
            if($prescid)
            OutsidePrescription($doc,$r,$pid,$prescid);
        }
    }
    else
    {
        OutsidePrescription($doc,$r,$pid,0);
    }    
    if($res_presc<$GLOBALS['total_count'])
    PatientMedication($doc,$r,$pid,$med_limit);
}
$xml = $doc->saveXML();
$xml = preg_replace('/"/',"'",$xml);
//echo $xml."<br><br>";
$xml = stripStrings($xml,array('&#xD;'=>'','\t'=>'','\r'=>'','\n'=>''));
if($msg)
{
    echo htmlspecialchars( xl('The following fields have to be filled to send request.'), ENT_NOQUOTES);
    echo "<br>";
    echo $msg;
    die;
}
//################################################
//XML GENERATED BY OPENEMR
//################################################
//$fh=fopen('click_xml.txt','a');
//fwrite($fh,$xml);
//echo $xml;
//die;
//################################################
if(!extension_loaded('curl'))
{
    echo htmlspecialchars( xl('PHP CURL module should be enabled in your server.'), ENT_NOQUOTES);die;
}
$error = checkError($xml);
if($error==0)
{
    if($page=='compose'){
        sqlQuery("update patient_data set soap_import_status=1 where pid=?",array($pid));
    }
    elseif($page=='medentry'){
        sqlQuery("update patient_data set soap_import_status=3 where pid=?",array($pid));
    }
    $prescArr=explode(':',$prescIds);
    foreach($prescArr as $prescid)
    {
        sqlQuery("update prescriptions set erx_uploaded='1', active='0' where patient_id=? and id=?",array($pid,$prescid));
    }
?>
    <script language='JavaScript'>
    <?php require($GLOBALS['srcdir'] . "/restoreSession.php"); ?>
    </script>
    <form name='info' method='post' action="<?php echo getErxPath()?>" onsubmit='return top.restoreSession()'>
        <input type='submit' style='display:none'>
        <input type='hidden' id='RxInput' name='RxInput' value="<?php echo $xml;?>">
    </form>
    <script type="text/javascript" src="../library/js/jquery.1.3.2.js"></script>
    <script type='text/javascript'>
    document.forms[0].submit();
    </script>
<?php
}
else
{
    echo htmlspecialchars( xl('NewCrop call failed', ENT_NOQUOTES));
}
?>
