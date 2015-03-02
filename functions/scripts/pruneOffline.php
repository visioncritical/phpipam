<?php

  // include required scripts
  require_once( dirname(__FILE__) . '/../functions.php' );
  require_once( dirname(__FILE__) . '/../functions-mail.php');
  require_once( dirname(__FILE__) . '/../scan/config-scan.php');

  //config
  $seconds = 86400; // The range which hosts can be offline before they're pruned
  $email = true; // Whether or not to email at all
  $emailText = false; // Email HTML or not

  // You can specify which type of hosts you want to expire by passing the appropriate # found in the state db column 
  // (default is active, must be passed through an array, and empty array means all)
  // 0 = offline
  // 1 = active
  // 2 = reserved
  // 3 = offline
  $hosts = getExpiredOfflineHosts ($seconds, array(1));
  removeExpiredOfflineHosts ($seconds, array(1));

  if(sizeof($hosts)>0 && $email) {
    //send text array, cron will do that by default if you don't redirect output > /dev/null 2>&1
    //this will be unformated (i.e. no Transform2long on the ip_addr field)
    if($emailText) {
      print_r($hosts);
    }
    //html
    else {
      $mail['from']     = "$settings[siteTitle] <ipam@$settings[siteDomain]>";
      $mail['headers']  = 'From: ' . $mail['from'] . "\r\n";
      $mail['headers'] .= "Content-type: text/html; charset=utf8" . "\r\n";
      $mail['headers'] .= 'X-Mailer: PHP/' . phpversion() ."\r\n";

      //subject
      $mail['subject']  = "phpIPAM pruned expired hosts ".date("Y-m-d H:i:s");

      //header
      $html[] = "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>";
      $html[] = "<html>";
      $html[] = "<head></head>";
      $html[] = "<body>";
      //title
      $html[] = "<h3>phpIPAM pruned ".sizeof($hosts)." offline hosts</h3>";
      //table
      $html[] = "<table style='margin-left:10px;margin-top:5px;width:auto;padding:0px;border-collapse:collapse;border:1px solid gray;'>";
      $html[] = "<tr>";
      $html[] = " <th style='padding:3px 8px;border:1px solid silver;border-bottom:2px solid gray;'>IP</th>";
      $html[] = " <th style='padding:3px 8px;border:1px solid silver;border-bottom:2px solid gray;'>Hostname</th>";
      $html[] = " <th style='padding:3px 8px;border:1px solid silver;border-bottom:2px solid gray;'>Subnet</th>";
      $html[] = " <th style='padding:3px 8px;border:1px solid silver;border-bottom:2px solid gray;'>Section</th>";

      $html[] = "</tr>";
      //Changes
      foreach($hosts as $index) {
        //set subnet
        $subnet = getSubnetDetails($index['subnetId']);
        $subnetPrint = Transform2long($subnet['subnet'])."/".$subnet['mask']." - ".$subnet['description'];
        //set section
        $section = getSectionDetailsById($subnet['sectionId']);
        $sectionPrint = $section['name']." (".$section['description'].")";

        $html[] = "<tr>";
        $html[] = " <td style='padding:3px 8px;border:1px solid silver;'>".Transform2long($index['ip_addr'])."</td>";
        $html[] = " <td style='padding:3px 8px;border:1px solid silver;'>".$index['dns_name']."</td>";
        $html[] = " <td style='padding:3px 8px;border:1px solid silver;'><a href='$settings[siteURL]".create_link("subnets",$section['id'],$subnet['id'])."'>$subnetPrint</a></td>";
        $html[] = " <td style='padding:3px 8px;border:1px solid silver;'><a href='$settings[siteURL]".create_link("subnets",$section['id'])."'>$sectionPrint</a></td>";//

        $html[] = "</tr>";
      }
      $html[] = "</table>";
      //footer

      //end
      $html[] = "</body>";
      $html[] = "</html>";

      //save to array
      $mail['content'] = implode("\n", $html);

      //send to all admins
      sendStatusUpdateMail($mail['content'], $mail['subject']);
    }
  }
?>