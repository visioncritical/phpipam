<?php

function fpingHosts ($ipRange, $count, $fpingPath) {
  $cmd = $fpingPath." -r 0 -c 1 -g ".$ipRange." 2> /dev/null | awk '{print \$1}'";
  exec($cmd, $output, $retval);
  return $output;
}

// function provided by Sanaa Rayane
function cidrToRange($cidr) {
  $cidr = explode('/', $cidr);
  $range = long2ip((ip2long($cidr[0])) & ((-1 << (32 - (int)$cidr[1]))));
  $range.= " ";
  $range.= long2ip((ip2long($cidr[0])) + pow(2, (32 - (int)$cidr[1])) - 1);
  return $range;
} 

// include required scripts
require_once( dirname(__FILE__) . '/../functions.php' );
require_once( dirname(__FILE__) . '/fpingThread.php');
require_once( dirname(__FILE__) . '/../functions-mail.php');
require_once( dirname(__FILE__) . '/../scan/config-scan.php');

// config
$email = true;                  //set mail with status diff to admins
$emailText = false;             //format to send mail via text or html
$count = 1;                     //number of pings to send
$numberOfThreads = 3;           //number of threads to run
$fpingPath = '/usr/sbin/fping'; //path to fping

// test to see if threading is available
if(!Thread::available())  { $threads = false; } //pcntl php extension required
else        { $threads = true; }

//get all subnets that are to be included
$subnets = getSubnetsToDiscover ();

//get settings
$settings = getAllSettings();

//verify that ping path is correct
if(!file_exists($fpingPath)) {
  print "Invalid ping path! You must set the fping path inside of this script (fpingDiscoveryCheck.php)\n";
}
//threads not supported, scan 1 by one - it is highly recommended to enable threading for php
elseif(!$threads) {
  //print warning
  print "Warning: Threading is not supported!\n";
}
else {
  //store added IPs
  $archive = array();
  //get size of all of the subnets
  $size = sizeof($subnets);

  //DISCOVERY

  $z = 0; //subnet array index
  for ($m=0; $m<=$size; $m += $numberOfThreads) {
    // create threads
    $threads = array();

    //fork processes
    for ($i = 0; $i <= $numberOfThreads && $i <= $size; $i++) {
      //only if index exists!
      if(isset($subnets[$z])) {
        $threads[$z] = new Thread( 'fpingHosts' );
        //$threads[$z]->start( Transform2long($subnets[$z]['subnet'])."/".$subnets[$z]['mask'], $count, $fpingPath, true );
        $threads[$z]->start( cidrToRange( Transform2long($subnets[$z]['subnet'])."/".$subnets[$z]['mask'] ), $count, $fpingPath, true );
      }
      $z++; //next index
    }

    //code graciously given by Ricardo Sanchez (along with the modifications to fpingThread.php)
    //http://tudorbarbu.ninja/multithreading-in-php/#comment-87
    while (!empty( $threads )) {
      foreach($threads as $index => $thread) {
          $child_pipe = "/tmp/pipe_".$thread->getPid();

          if (file_exists($child_pipe)) {
              $file_descriptor = fopen( $child_pipe, "r");
              $child_response = "";
              while (!feof($file_descriptor)) {
                  $child_response .= fread($file_descriptor, 8192);
              }
              //we have the child data in the parent, but serialized:
              $ips[$subnets[$index]['id']] = unserialize( $child_response );

              //now, child is dead, and parent close the pipe
              unlink( $child_pipe );
              unset($threads[$index]);
          }
      }
      //parent must sleep for a while, because we don't want to achieve 100% CPU load!
      usleep(200000);
    } //end while
  } //end for

  // remove existing IPs
  foreach($subnets as $s) {
    $addresses = getIpAddressesBySubnetId ($s['id']);
    foreach($addresses as $a) {
      $key = array_search(Transform2long($a['ip_addr']), $ips[$s['id']]);
      if($key!==false) {
        unset($ips[$s['id']][$key]);
      }
    }
  }

  //$ips format
  // Array
  // (
  //   [subnetId] => Array
  //   (
  //     [0] => xx.xx.xx.xx
  //     [1] => xx.xx.xx.xx
  //   }
  // )

  //INSERTING

  if(!empty($ips)) {
    foreach($ips as $k=>$subnet) {
      if(!empty($subnet)) {
        foreach($subnet as $ip) {
          $submission = array();
          $submission['subnetId'] = $k;
          $submission['ip_addr'] = Transform2decimal($ip);
          // try to resolve
          $submission['dns_name'] = ResolveDnsName ($submission['ip_addr']);
          if($submission['dns_name']['class']=="resolved") {
            $submission['dns_name'] = $submission['dns_name']['name'];
          }
          else {
            $submission['dns_name'] = "";
          }

          // insert
          if(!insert_discovered_ip($submission))  { print "Cannot add discovered IP ".$submission['ip_addr']."\n"; }

          if(!$submission['dns_name'] == "") {
            $archive[$k][$submission['dns_name']] = $submission['ip_addr'];
          }
          else {
            //if we don't do this, entries that don't resolve will be overwritten
            $archive[$k][] = $submission['ip_addr'];
          }
        } //end foreach
      } //end if
      $totalIPs += sizeof($archive[$k]);
    } //end foreach
  } //end if

  //REPORTING

  if($totalIPs>0 && $email) {
    //send text array, cron will do that by default if you don't redirect output > /dev/null 2>&1
    if($emailText) {
      print_r($stateDiff);
    }
    //html
    else {

      $mail['from']      = "$settings[siteTitle] <ipam@$settings[siteDomain]>";
      $mail['headers']   = 'From: ' . $mail['from'] . "\r\n";
      $mail['headers']  .= "Content-type: text/html; charset=utf8" . "\r\n";
      $mail['headers']  .= 'X-Mailer: PHP/' . phpversion() ."\r\n";

      //subject
      $mail['subject']  = "phpIPAM new addresses detected ".date("Y-m-d H:i:s");

      //header
      $html[] = "<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01 Transitional//EN' 'http://www.w3.org/TR/html4/loose.dtd'>";
      $html[] = "<html>";
      $html[] = "<head></head>";
      $html[] = "<body>";
      //title
      $html[] = "<h3>phpIPAM found ".$totalIPs." new hosts</h3>";
      //table
      $html[] = "<table style='margin-left:10px;margin-top:5px;width:auto;padding:0px;border-collapse:collapse;border:1px solid gray;'>";
      $html[] = "<tr>";
      $html[] = " <th style='padding:3px 8px;border:1px solid silver;border-bottom:2px solid gray;'>IP</th>";
      $html[] = " <th style='padding:3px 8px;border:1px solid silver;border-bottom:2px solid gray;'>Hostname</th>";
      $html[] = " <th style='padding:3px 8px;border:1px solid silver;border-bottom:2px solid gray;'>Subnet</th>";
      $html[] = " <th style='padding:3px 8px;border:1px solid silver;border-bottom:2px solid gray;'>Section</th>";

      $html[] = "</tr>";
      //Changes
      foreach($archive as $index => $change) {
        //set subnet
        $subnet = getSubnetDetails($index);
        $subnetPrint = Transform2long($subnet['subnet'])."/".$subnet['mask']." - ".$subnet['description'];
        //set section
        $section = getSectionDetailsById($subnet['sectionId']);
        $sectionPrint = $section['name']." (".$section['description'].")";

        foreach($change as $dns => $ip) {
          //otherwise we get integers in our report
          if(is_int($dns)) {
            $dns='';
          }
          $html[] = "<tr>";
          $html[] = " <td style='padding:3px 8px;border:1px solid silver;'>".Transform2long($ip)."</td>";
          $html[] = " <td style='padding:3px 8px;border:1px solid silver;'>$dns</td>";
          $html[] = " <td style='padding:3px 8px;border:1px solid silver;'><a href='$settings[siteURL]".create_link("subnets",$section['id'],$subnet['id'])."'>$subnetPrint</a></td>";
          $html[] = " <td style='padding:3px 8px;border:1px solid silver;'><a href='$settings[siteURL]".create_link("subnets",$section['id'])."'>$sectionPrint</a></td>";//

          $html[] = "</tr>";
        }
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
} //end else

?>