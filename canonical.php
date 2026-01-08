<?php
/*
 Copyright 2026-2026 Bo Zimmerman
 
 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at
 
 http://www.apache.org/licenses/LICENSE-2.0
 
 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
 */
/* TODO:  User editor
*/
	function makeToken()
	{
		if (function_exists('openssl_random_pseudo_bytes'))
			return bin2hex(openssl_random_pseudo_bytes(40));
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < 80; $i++)
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		return $randomString;
	}
	function mtyp($fn)
	{
		$all_types = array(
		 'png' => 'image/png',
		 'jpe' => 'image/jpeg',
		 'jpeg' => 'image/jpeg',
		 'jpg' => 'image/jpeg',
		 'gif' => 'image/gif'
		);
		$ext = explode('.', $fn);
		$ext = strtolower(end($ext));
		if (array_key_exists($ext, $all_types))
			return $all_types[$ext];
		return '';
	};
	class CanonType 
	{
		const Login = 0;
		const Forgot = 1;
		const Account = 2;
		const Image = 3;
	};
	class TypeLimit
	{
		const Login = 3;
		const Account = 3;
		const Image = 100;
		const Forgot = 5;
	};
	require "canoniconfig.php";
	//ini_set('display_errors', 1);
	//ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
	$curcat = isset($_GET["cat"]) ? $_GET["cat"] : NULL;
	$curprod = isset($_GET["prod"]) ? $_GET["prod"] : NULL;
	$curmodel = isset($_GET["model"]) ? $_GET["model"] : NULL;
	$curvariation = isset($_GET["variation"]) ? $_GET["variation"] : NULL;
	$command = isset($_GET["command"]) ? $_GET["command"] : NULL;
	$search = isset($_POST["SEARCH"]) ? $_POST["SEARCH"] : NULL;
	if($search == NULL)
		$search = isset($_GET["SEARCH"]) ? $_GET["SEARCH"] : NULL;
	$privs = 0;
	$curname = '';
	$urlenc = '';
	$urladd = (($search == NULL)||(trim($search)=='')) ? '' : '&SEARCH='.urlencode($search);
	$editflag = 0;
	$showerror = '';
	$getcookie = null;
	$cookiecookie = null;
	$cookie = null;
	
	function setUrlEnc()
	{
		$u = '';
		global $curcat;
		global $curprod;
		global $curmodel;
		global $editflag;
		global $search;
		if(isset($curcat) && ($curcat != ''))
		{
			$u .= 'cat='.urlencode($curcat);
			if(isset($curprod) && ($curprod != ''))
			{
				$u .= '&prod='.urlencode($curprod);
				if(isset($curmodel) && ($curmodel != ''))
					$u .= '&model='.urlencode($curmodel);
			}
		}
		if(isset($editflag) && ($editflag == 1))
			$u .= '&edit=1';
		if(isset($search) && ($search != NULL) && (trim($search) != ''))
			$u .= '&SEARCH='.urlencode($search);
		return $u;
	}

try {
	session_start();
	$pdo = new PDO('mysql:host=' . $CANON_DB_HOST . ';dbname=' . $CANON_DB_PREFIX . 'canonical', $CANON_DB_USER, $CANON_DB_PASSWORD);
	if (!isset($_SESSION['csrf_token']))
		$_SESSION['csrf_token'] = makeToken();
	if ($_SERVER['REQUEST_METHOD'] === 'POST') 
	{
		if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])
			die('Invalid request');
	}

	if ($command == 'IMG')
	{
		if(isset($curmodel)
		&& isset($curvariation)
		&& isset($_GET["iname"])
		&& (trim($curmodel) != '')
		&& (trim($curvariation) != ''))
		{
			$curnm = $_GET["iname"];
			if(isset($curnm) && (trim($curnm) != ''))
			{
				$width = 0;
				$simgwidth = 400;
				if(isset($_GET["width"]) && (strtolower($_GET["width"])=='small'))
					$width = $simgwidth;
				$stmt = $pdo->prepare("SELECT PHOTO FROM PHOTOS WHERE MODEL=:md AND VARI=:va AND PHOTONAME=:pn AND WIDTH=".$width);
				$stmt->execute(array(':md' =>$curmodel, ':va' => $curvariation, ':pn' => $curnm));
				if($row = $stmt->fetch()) 
				{
					$data = $row["PHOTO"];
					header ("Content-type: ".mtyp($curnm));
					header ("Content-length: ".strlen($data));
					print ($data);
					exit(200);
				} 
				else 
				if($width != 0)
				{
					$pdo->query("DELETE FROM THROTTLE WHERE EXPIRE < ".time());
					$stmt = $pdo->prepare("SELECT * FROM PHOTOS WHERE MODEL=:md AND VARI=:va AND PHOTONAME=:pn AND WIDTH=0");
					$stmt->execute(array(':md' =>$curmodel, ':va' => $curvariation, ':pn' => $curnm));
					if($row = $stmt->fetch()) 
					{
						$data = $row["PHOTO"];
						$date = $row["POSTDATE"];
						$ordinal = $row["ORDINAL"];
						$bigimg = imagecreatefromstring($data);
						$bigwidth  = imagesx($bigimg);
						$bigheight = imagesy($bigimg);
						$thumb = '';
						if($bigwidth > $simgwidth)
						{
							$proportion = 400.0 / $bigwidth;
							$newheight = $bigheight * $proportion;
							$thumb = imagecreatetruecolor(400, $newheight);
							imagecopyresized($thumb, $bigimg, 0, 0, 0, 0, 400, $newheight, $bigwidth, $bigheight);
							imagedestroy($bigimg);
						}
						else
							$thumb = $bigimg;
						$ext = explode('.', $curnm);
						$ext = strtolower(end($ext));
						ob_start();
						switch($ext)
						{
						case 'jpg':
						case 'jpe':
						case 'jpeg':
							imagejpeg($thumb);
							break;
						case 'png':
							imagepng($thumb);
							break;
						case 'gif':
							imagegif($thumb);
							break;
						}
						$data = ob_get_clean();
						imagedestroy($thumb);
						$bigsql = "INSERT INTO PHOTOS (MODEL, VARI, APPROVED, DESCRIP, PHOTOURL, PHOTONAME, PHOTO, NAME, POSTDATE, ORDINAL, WIDTH) ";
						$bigsql .= "VALUES (:md, :va, 1, '', null, :pn, :pp, '', :pd, :or, 400)";
						$stmt = $pdo->prepare($bigsql);
						$stmt->execute(array(':md' =>$curmodel, ':va' => $curvariation, ':pn' => $curnm,
											 ':pp' => $data, ':pd' => $date, ':or' =>$ordinal ));
						header ("Content-type: ".mtyp($curnm));
						print ($data);
						exit(200);
					}
				}
			}
		}
		exit(404);
	}

	function doLog($msg)
	{
		global $pdo;
		global $curname;
		if(isset($curname) && ($curname != '') && ($msg != ''))
		{
			if(rand(0,10)<2)
			{
				$lstmt = $pdo->query("SELECT MAX(ORDINAL) M FROM VISLOG");
				$maxord = 0;
				if($lrow = $lstmt->fetch())
					$maxord = (int)$lrow["M"] - 200;
				$oldest = time() - 2628288; // one month
				$pdo->query("DELETE FROM VISLOG WHERE ORDINAL < ".$maxord." AND EDATE < ".$oldest);
			}
			$lstmt = $pdo->prepare("INSERT INTO VISLOG (NAME, EDATE, EVENT) VALUES (:nm, :ed, :ev)");
			$lstmt->execute(array(':nm' =>$curname, ':ed' => time(), ':ev' => $msg));
		}
	}

	function updateDate()
	{
		global $pdo;
		$date = new DateTime();
		$datef = date_format($date, 'F jS, Y');
		$dstmt = $pdo->prepare("UPDATE LASTUPDATE SET DATE=:da");
		$dstmt->execute(array(':da'=> $datef));
	}

	function doGroup($curprod)
	{
		global $pdo;
		$spaces = '																		   ';
		$cstmt = $pdo->prepare("SELECT CATNAME FROM CAT WHERE PRODUCED=:pd ORDER BY ORDINAL");
		$cstmt->execute(array(':pd' =>$curprod));
		while($cat = $cstmt->fetch())
		{
			$curcat = $cat['CATNAME'];
			echo $curcat . ": ";
			$catlen = strlen($curcat) + 2;
			$notes = array();
			$stmt = $pdo->prepare("SELECT DESCRIP FROM NOTE WHERE HEADER=:nm");
			$stmt->execute(array(':nm' =>$curcat."_".$curprod));
			if($row = $stmt->fetch())
				array_push($notes, $row["DESCRIP"]);
			else {
				$stmt = $pdo->prepare("SELECT NOTE FROM CAT WHERE CATNAME=:nm AND PRODUCED=:pd");
				$stmt->execute(array(':nm' =>$curcat, ':pd' => $curprod));
				if($row = $stmt->fetch())
					array_push($notes, $row["NOTE"]);
			}
			$oldnotes = $notes;
			$notes = array();
			foreach($oldnotes as $on)
			{
				foreach(explode("\n", $on) as $n)
					array_push($notes, $n);
			}
			$firstlinedone = 0;
			$enddex = 79 - $catlen;
			foreach($notes as $n)
			{
				if ($firstlinedone == 1)
					echo substr($spaces, 0, $catlen);
				if (strlen($n) <= $enddex)
					echo $n;
				else
				{
					$n = trim($n);
					while (strlen($n) > $enddex)
					{
						$x = (strpos($n, "\r") < 0) ? strpos($n, "\n") : strpos($n, "\r") ;
						if(($x > $enddex) || ($x <= 0))
						{
							$x = $enddex-1;
							while($n[$x] != ' ')
								$x--;
						}
						$ns = trim(substr($n,0,$x));
						echo $ns;
						echo "\n";
						$n = trim(substr($n,$x));
						echo substr($spaces,0,$catlen);
					}
					echo $n;
				}
				echo "\n";
				$firstlinedone = 1;
			}
			$stmt = $pdo->prepare("SELECT MODEL,VARI,VERIFIED,SHORTBLURB FROM ENTRY WHERE CATNAME=:nm AND PRODUCED=:pd ORDER BY MODEL,VARI");
			$stmt->execute(array(':nm' =>$curcat, ':pd' => $curprod));
			while($row = $stmt->fetch())
			{
				$curmodel = $row["MODEL"];
				$curvariation = $row["VARI"];
				if($row["VERIFIED"]==1)
					echo "* ";
				else
					echo "  ";
				$modelnm = $curmodel;
				if(($curvariation > 0) && ((strpos($curmodel,'?')>=0) || (strpos(strtolower($curmodel),"xx")>=0)))
					$modelnm .= " ".$row["VARI"];
				echo $modelnm;
				$ll = 74 - strlen($modelnm);
				if (strlen($modelnm)+2 < 15)
				{
					echo substr($spaces,0,15-(strlen($modelnm)+2));
					$ll = $ll - (15-(strlen($modelnm)+2));
				}
				echo " ";
				$ll = $ll - 1;
				$d = $row["SHORTBLURB"];
				$ostmt = $pdo->prepare("SELECT CODE FROM OWNER o INNER JOIN OWNERS s ON o.NAME=s.NAME WHERE s.MODEL=:md AND s.VARI=:va and o.CODE != ''");
				$ostmt->execute(array(':md' => $curmodel, ':va' => $curvariation));
				$cd = '';
				if($orow = $ostmt->fetch())
					$cd = $orow["CODE"];
				if (strlen($d) <= $ll)
				{
					echo $d;
					if($cd != '')
					{
						if (strlen($d) < $ll)
							echo substr($spaces, 0, $ll - strlen($d));
						echo $cd;
					}
				}
				else
				{
					$firstlinedone = 0;
					while (strlen($d) > $ll)
					{
						$x = (strpos($d, "\r") < 0) ? strpos($d, "\n") : strpos($d, "\r") ;
						if(($x > $ll) || ($x <= 0))
						{
							$x = $ll-1;
							while($d[$x] != ' ')
								$x--;
						}
						$ds = trim(substr($d,0,$x));
						echo $ds;
						if(($firstlinedone==0) && ($cd != ''))
						{
							$firstlinedone = 1;
							if (strlen($ds) < $ll)
								echo substr($spaces, 0, $ll-strlen($ds));
							echo $cd;
						}
						echo "\n";
						$d = trim(substr($d,$x));
						echo substr($spaces,0,16);
						$ll = 60;
					}
					echo $d;
				}
				echo "\n";
			}
			echo "\n";
		}
	}

	if ($command == 'FULLLIST')
	{
		header("Content-Type: text/plain");
		$stmt = $pdo->query("SELECT DATE FROM LASTUPDATE");
		if($row = $stmt->fetch())
			echo 'Last updated: '.$row["DATE"]."\n";
		$stmt = $pdo->query("SELECT DESCRIP FROM NOTE WHERE HEADER='INTRO'");
		while($row = $stmt->fetch())
			echo $row["DESCRIP"]."\n";
		echo "========================== Notes ==============================================\n";
		$stmt = $pdo->query("SELECT DESCRIP FROM NOTE WHERE HEADER='NOTES'");
		while($row = $stmt->fetch())
			echo $row["DESCRIP"]."\n";
		echo "======================Questions Still Left to Answer==========================\n";
		$stmt = $pdo->query("SELECT DESCRIP FROM NOTE WHERE HEADER='QUESTIONS'");
		while($row = $stmt->fetch())
			echo $row["DESCRIP"]."\n";
		echo str_repeat('=', floor((78 - strlen($CANON_APP_NAME) - 2) / 2)) . ' ' . $CANON_APP_NAME . ' ' . str_repeat('=', ceil((78 - strlen($CANON_APP_NAME) - 2) / 2)) . "\n";
		echo "\n";
		echo "---------------------Products Produced In Some Quantity:----------------------\n";
		echo "\n";
		doGroup(1);
		echo "\n";
		echo "------------------ Models Never Produced or Marketed:----------------\n";
		echo "\n";
		doGroup(0);
		echo "\n";
		echo "-----------------------------Owner Mnemonics:---------------------------------\n";
		echo "\n";
		$stmt = $pdo->query("SELECT NAME,EMAIL,CODE FROM OWNER WHERE CODE != '' AND NAME IN (SELECT NAME FROM OWNERS) ORDER BY CODE");
		$eol = false;
		while($row = $stmt->fetch()) 
		{
			echo substr($row['CODE'].'   ',0,2);
			echo "	";
			if($eol)
			{
				echo $row['NAME'];
				echo "\n";
				$eol = false;
			}
			else
			{
				echo substr($row['NAME'].'											 ',0,32);
				echo "  ";
				$eol = true;
			}
		}
		echo "\n";
		exit(200);
	}

	$urlenc = setUrlEnc();
	$getcookie = isset($_GET['token']) ? $_GET['token'] : NULL;
	$cookie = $getcookie;
	if ((!isset($cookie)) || ($cookie == null) || ($cookie == ''))
	{
		$getcookie = null;
		$cookiecookie = isset($_COOKIE['canon_session']) ? $_COOKIE['canon_session'] : NULL;
		$cookie = $cookiecookie;
	}
	if (isset($cookie) && ($cookie != null) && (strlen($cookie) > 0))
	{
		$stmt = $pdo->prepare("SELECT NAME,EXPIRE FROM TOKEN WHERE TOKEN=:tk");
		$stmt->execute(array(':tk' =>$cookie));
		if($row = $stmt->fetch()) 
		{
			$tmpname = $row["NAME"];
			if(time() < $row["EXPIRE"])
			{
				$stmt = $pdo->prepare("SELECT ACCESS FROM OWNER WHERE NAME=:nm");
				$stmt->execute(array(':nm' =>$tmpname));
				if($row = $stmt->fetch()) 
				{
					$privs = $row["ACCESS"];
					$curname = $tmpname;
					if($command == 'LOGOUT')
					{
						$stmt = $pdo->prepare("DELETE FROM TOKEN WHERE NAME=:nm");
						$stmt->execute(array(':nm' =>$curname));
						$curname = '';
						$privs = 0;
						unset($_COOKIE["canon_session"]);
						setcookie('canon_session', null, -1, '/', '', false, true, 'Lax');
					}
					else
					if(($cookiecookie == null) && ($getcookie != null))
					{
					    setcookie('canon_session', $getcookie, (time()+42150), '/', '', false, true, 'Lax');
						// they have a perm token now
						$stmt = $pdo->prepare("UPDATE TOKEN SET EXPIRE=:ex WHERE NAME=:nm");
						$stmt->execute(array(':ex' =>(time()+42150), ':nm' =>$tmpname));
					}
					else
					if($privs > 1)
					{
						$editflag = isset($_GET["edit"]) ? $_GET["edit"] : NULL;
						if (!isset($editflag) || ($editflag == null) || ($editflag == ''))
							$editflag = isset($_POST["edit"]) ? $_POST["edit"] : NULL;
						if ($editflag == 1)
						{
							$urlenc .= (strlen($urlenc) > 0) ? '&edit=1' : 'edit=1';
							$urladd .= '&edit=1';
						}
						else
							$editflag = 0;
					}
				}
			}
		}
	}
}
catch(PDOException $e)
{
	error_log("Database error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
	echo '<HTML><BODY><h2>Service Temporarily Unavailable</h2>';
	echo '<p>We are experiencing technical difficulties. Please try again later.</p>';
	echo '</BODY></HTML>';
	exit(500); // Use proper HTTP status code
}
function atLinkFix($txt)
{
	global $pdo;
	$x = strpos($txt, '@', 0);
	if($x >= 0)
	{
		$y = strpos($txt, ' ', $x+1);
		if(!$y)
			$y=strlen($txt);
		$rawkey = substr($txt, $x+1, $y-($x+1));
		$z = strpos($rawkey, '#');
		$v = 0;
		$key = $rawkey;
		if($z > 0)
		{
			$key = substr($rawkey,0, $z);
			$v = (int)substr($rawkey, $z+1);
		}
		if(strlen(trim($key))>0)
		{
			$key = str_replace('_', ' ', $key);
			$stmt = $pdo->prepare("SELECT CATNAME,PRODUCED FROM ENTRY WHERE MODEL=:md and VARI=:va");
			$stmt->execute(array(':md' =>$key, ':va' =>$v));
			if($row = $stmt->fetch())
			{
				$nc = $row["CATNAME"];
				$np = $row["PRODUCED"];
				global $urladd;
				$t = substr($txt,0,$x) . '<a href="?cat='.urlencode($nc).'&prod='.urlencode($np).'&model='.urlencode($key).'&variation='.urlencode($v).$urladd.'"><FONT class="category-header">'.urlencode($key).'</FONT></a>';
				$txt = ($y >= strlen($txt)) ? $t : ($t . substr($txt,$y) );
			}
			$x = strpos($txt, '@', $x+1);
		}
		else
			$x = false;
   }
   return $txt;
}
?>
<HTML>
<HEAD>
<TITLE><?php echo $CANON_APP_NAME ?></TITLE>
<link rel="stylesheet" href="canonstyle.css">
<STYLE>
A { text-decoration: none; }
</STYLE>
</HEAD>
<body background = "<?php echo $CANON_IMG_BACKGROUND ?>">
<TABLE class="header-container" BORDER=1 CELLPADDING=0 CELLSPACING=0 WIDTH=100%><tr><td>
<TABLE class="header-container" BORDER=0 WIDTH=100%>
  <tr>
  <td width=5%>
	<a href="<?php echo $CANON_WEB_HOST?>"><img width=32 height=32 src="<?php echo $CANON_IMG_TINYLOGOL ?>"></a>
  </td>
  <TD colspan=5 align=center>
  <h2><a href="?" style="text-decoration-line: none"><FONT class="app-title"><?php echo $CANON_APP_BANNER_NAME ?></FONT></a>
  <?php
	if(isset($_GET['info']) && ($_GET['info'] == 1))
	{
		echo "</h2>\n<pre>";
		$stmt = $pdo->prepare("SELECT DESCRIP FROM NOTE WHERE HEADER='INTRO'");
		$stmt->execute();
		if($row = $stmt->fetch())
					echo htmlentities($row["DESCRIP"]);
		echo "\n</pre><pre align=left>\n<b>Notes</b>:\n";
		$stmt = $pdo->prepare("SELECT DESCRIP FROM NOTE WHERE HEADER='NOTES'");
		$stmt->execute();
		if($row = $stmt->fetch())
					echo htmlentities($row["DESCRIP"]);
		echo "\n<b>Questions</b>:\n";
		$stmt = $pdo->prepare("SELECT DESCRIP FROM NOTE WHERE HEADER='QUESTIONS'");
		$stmt->execute();
		if($row = $stmt->fetch())
					echo htmlentities($row["DESCRIP"]);
		echo '</pre><h2>';
	}
	else
	{
		echo '<a href="?info=1&'.$urlenc.'"><FONT class="info-icon">?</FONT></a>';
	}
  ?>
  </h2></TD>
  <td width=5% ALIGN=RIGHT>
	<a href="<?php echo $CANON_WEB_HOST?>"><img WIDTH=32 HEIGHT=32 src="<?php echo $CANON_IMG_TINYLOGOR; ?>"></a>
  </td>
  </TR>
</TABLE></TD></TR></TABLE>
<TABLE BORDER=1 WIDTH=100%>
<TR><TD WIDTH=30% VALIGN=TOP>
	<?php
	    require 'vendor/autoload.php';
		use PHPMailer\PHPMailer\PHPMailer;
		use PHPMailer\PHPMailer\SMTP;
		use PHPMailer\PHPMailer\Exception;
		$userip = $_SERVER['REMOTE_ADDR'];
		if ((isset($_POST['email']))
		&& ($_POST['email'] !='')
		&& (isset($_POST['password']))
		&& ($_POST['password'] !=''))
		{
			$aemail = $_POST['email'];
			$pdo->query("DELETE FROM THROTTLE WHERE EXPIRE < ".time());
			$stmt = $pdo->prepare("SELECT COUNT(*) C FROM THROTTLE WHERE TYPE=:ty AND EMAIL=:em AND EXPIRE > :tm");
			$stmt->execute(array(':ty' => CanonType::Login, ':em' => $_POST['email'], ':tm' => time()));
			if(($row = $stmt->fetch())  && ($row["C"] >= TypeLimit::Login))
				echo '<FONT class="error-message">Too many login attempts today.</FONT><BR>';
			else
			{
				$stmt = $pdo->prepare("SELECT NAME,ACCESS FROM OWNER WHERE EMAIL=:em AND PASSWORD=:pw");
				$stmt->execute(array(':em' =>$_POST["email"], ':pw' =>$_POST["password"]));
				if($row = $stmt->fetch()) 
				{
					$privs = $row["ACCESS"];
					$curname = $row["NAME"];
					$stmt = $pdo->prepare("DELETE FROM TOKEN WHERE NAME=:nm");
					$stmt->execute(array(':nm' =>$curname));
					$stmt = $pdo->prepare("DELETE FROM THROTTLE WHERE TYPE<:ty AND EMAIL=:em OR IP=:ip"); // login cures much
					$stmt->execute(array(':ty' => CanonType::Account, ':em' => $_POST['email'], ':ip' => $userip));
					$randomString = makeToken();
					$stmt = $pdo->prepare("INSERT INTO TOKEN (TOKEN,NAME,EXPIRE) VALUES (:tk,:nm,:ep)");
					$stmt->execute(array(':nm' =>$curname, ':tk' =>$randomString, ':ep' =>(time()+42150)));
					setcookie('canon_session', $randomString, (time()+42150), '/', '', false, true, 'Lax');
					$getcookie = null;
					$cookiecookie = $randomString;
				}
			}
		}

		if($curname != '')
		{
			if(isset($_POST['newpassword'])
			&& ($_POST['newpassword'] != null)
			&& (trim($_POST['newpassword']) != ''))
			{
				$newpw = trim($_POST['newpassword']);
				echo '<FONT class="success-message">Password changed!</FONT><BR>';
				$stmt = $pdo->prepare("UPDATE OWNER SET PASSWORD=:pw WHERE NAME=:nm");
				$stmt->execute(array(':nm' =>$curname, ':pw' =>$newpw));
			}
			echo "<FONT class=\"login-text\">";
			echo "Welcome back ".$curname."!";
			echo '&nbsp;&nbsp;<a href="?command=LOGOUT"><FONT class="login-text">(logout)</FONT></a>';
			if($privs > 1)
			{
				if($editflag == 1)
					echo '&nbsp;&nbsp;<a href="?donothing=0&'.$urlenc.'&edit=0"><FONT class="login-text">(->view)</FONT></a>';
				else
					echo '&nbsp;&nbsp;<a href="?donothing=0&'.$urlenc.'&edit=1"><FONT class="login-text">(->edit)</FONT></a>';
				echo '&nbsp;&nbsp;<a href="?command=SHOWLOG&'.$urlenc.'&edit=1"><FONT class="login-text">(log->)</FONT></a>';
			}
			echo "</FONT><BR>\n";
		}
		else
		if(isset($_POST['forgot']) && ($_POST['forgot'] == 'on'))
		{
			if ((isset($_POST['email'])) && ($_POST['email'] !=''))
			{
				$aemail = $_POST['email'];
				$pdo->query("DELETE FROM THROTTLE WHERE EXPIRE < ".time());
				$stmt = $pdo->prepare("SELECT COUNT(*) C FROM THROTTLE WHERE TYPE=:ty AND EMAIL=:em AND EXPIRE > :tm");
				$stmt->execute(array(':ty' => CanonType::Forgot, ':em' => $_POST['email'], ':tm' => time()));
				if(($row = $stmt->fetch())  && ($row["C"] >= TypeLimit::Forgot))
					echo '<FONT class="error-message">Too many forgot pw attempts today.</FONT><BR>';
				else
				{
					$stmt = $pdo->prepare("INSERT INTO THROTTLE (TYPE, IP, EMAIL, EXPIRE) VALUES (:ty, :ip, :em, :tm)");
					$stmt->execute(array(':ty' => CanonType::Forgot, ':ip' => $userip, ':em' => $aemail, ':tm' => (time()+86400)));
					$stmt = $pdo->prepare("SELECT NAME,ACCESS FROM OWNER WHERE EMAIL=:em");
					$stmt->execute(array(':em' => $aemail));
					$proceed = 1;
					if ($row = $stmt->fetch())
					{
						$aname = $row["NAME"];
						$stmt = $pdo->prepare("SELECT EXPIRE FROM TOKEN WHERE NAME=:nm");
						$stmt->execute(array(':nm' =>$aname));
						if ($row = $stmt->fetch()) 
						{
							$expire = $row["EXPIRE"];
							if ((time() < $expire)&&(time() > $expire - 540))
							{
								echo '<FONT class="error-message">GIVE IT A BLESSED MINUTE!</FONT><BR>';
								$proceed = 0;
							}
						}
						if ($proceed == 1)
						{
							$stmt = $pdo->prepare("DELETE FROM TOKEN WHERE NAME=:nm");
							$stmt->execute(array(':nm' =>$curname));
							$randomString = makeToken();
							$stmt = $pdo->prepare("INSERT INTO TOKEN (TOKEN,NAME,EXPIRE) VALUES (:tk,:nm,:ep)");
							$stmt->execute(array(':nm' =>$aname, ':tk' =>$randomString, ':ep' =>(time()+600)));
							$headers = 'From: ' . $CANON_MAIL_FROM_ADDRESS . "\r\n" .
										'Reply-To: ' . $CANON_MAIL_REPLYTO_ADDRESS . "\r\n" .
										'X-Mailer: PHP/' . phpversion();
							try {
								$mail = new PHPMailer(true); //Argument true in constructor enables exceptions
								$mail->IsSMTP(); // telling the class to use SMTP
								$mail->Host	   = $CANON_MAIL_SMTP_HOST; // SMTP server
								//$mail->SMTPDebug  = 1;
								$mail->SMTPAuth   = true;
								$mail->Port	   = $CANON_MAIL_SMTP_PORT;
								$mail->Username   = $CANON_MAIL_SMTP_AUTH_USERNAME;
								$mail->Password   = $CANON_MAIL_SMTP_AUTH_PASSWORD;
								$mail->From = $CANON_MAIL_FROM_ADDRESS;
								$mail->FromName = $CANON_MAIL_FROM_NAME;
								$mail->SMTPSecure = $CANON_MAIL_SMTP_SECURE_TYPE;
								$mail->AddAddress($aemail);
								//$mail->ReplyTo = $CANON_MAIL_REPLYTO_ADDRESS;
								$mail->isHTML(false);
								$mail->Subject = "Login to $CANON_APP_NAME!";
								$mail->Body = "If you didn't try to login, ignore this.\n"
								   ."If you forgot your password, go to "
								   . $CANON_WEB_HOST . "/canonical.php?token=".$randomString." "
								   ."Then scroll to the bottom and change your password!\n";
								$mail->AltBody = $mail->Body;
								$mail->send();
								echo '<FONT class="success-message">An email is on the way!</FONT><BR>';
							} 
							catch (Exception $e) 
							{
							    error_log("Mail error for user: " . $mail->ErrorInfo);
							    echo '<FONT class="error-message">Unable to send email. Please try again later or contact support.</FONT><BR>';
							}
						}
					}
					else
						echo '<FONT class="error-message">You must enter your email address to get a password token.</FONT><BR>';
				}
			}
			else
				echo '<FONT class="error-message">You must enter your email address to get a password token.</FONT><BR>';
		}
		else
		if(isset($_POST['newname']) && ($_POST['newname'] != null) && ($_POST['newname'] != '')
		&& isset($_POST['newemail']) && ($_POST['newemail'] != null) && ($_POST['newemail'] != '') )
		{
			$aemail=trim($_POST['newemail']);
			$aname=trim($_POST['newname']);
			$private = 0;
			if(isset($_POST['newprivate']) && ($_POST['newprivate'] != null))
			{
			  $aprivate=trim($_POST['newprivate']);
			  if ($aprivate == 'on')
				  $private = 1;
			}
			$stmt = $pdo->prepare("SELECT NAME FROM OWNER WHERE EMAIL LIKE :em OR NAME LIKE :nm");
			$stmt->execute(array(':em' => $aemail, ':nm' => $aname));
			if ($row = $stmt->fetch())
				echo '<FONT class="error-message">Account name or email already exists.</FONT><BR>';
			else
			{
				if((!isset($_COOKIE["canon_newacct"])) || ($_COOKIE["canon_newacct"] == ''))
				{
				    setcookie('canon_newacct', 'boo!',time()+83400, '/', '', false, true, 'Lax');
					$pdo->query("DELETE FROM THROTTLE WHERE EXPIRE < ".time());
					$stmt = $pdo->prepare("SELECT COUNT(*) C FROM THROTTLE WHERE TYPE=:ty AND IP=:ip AND EXPIRE > :tm");
					$stmt->execute(array(':ty' => CanonType::Account, ':ip' => $userip, ':tm' => time()));
					if(($row = $stmt->fetch())  && ($row["C"] >= TypeLimit::Account)) 
					{
						echo '<FONT class="error-message">Too many account creations today.</FONT><BR>';
						goto skip_create;
					}
					$checkStmt = $pdo->query("SELECT COUNT(*) as C FROM OWNER");
					$adminLevel = 0;
					if($checkRow = $checkStmt->fetch())
					{
						if($checkRow["C"] == 0)
							$adminLevel = 2; // first user gets admin privileges
					}
					$stmt = $pdo->prepare("INSERT INTO OWNER (NAME,EMAIL,PASSWORD,ACCESS,PRIVATE,CODE) VALUES (:nm, :em, '', :ac, :pv, '')");
					$stmt->execute(array(':nm' =>$aname, ':em' =>$aemail, ':ac' =>$adminLevel, ':pv' =>$private));
					$stmt = $pdo->prepare("DELETE FROM TOKEN WHERE NAME=:nm");
					$stmt->execute(array(':nm' =>$aname));
					$randomString = makeToken();
					$stmt = $pdo->prepare("INSERT INTO TOKEN (TOKEN,NAME,EXPIRE) VALUES (:tk,:nm,:ep)");
					$stmt->execute(array(':nm' =>$aname, ':tk' =>$randomString, ':ep' =>(time()+26600)));
					$headers = 'From: ' . $CANON_MAIL_FROM_ADDRESS . "\r\n" .
								'Reply-To: ' . $CANON_MAIL_REPLYTO_ADDRESS . "\r\n" .
								'X-Mailer: PHP/' . phpversion();
					try {
						$mail = new PHPMailer(true); //Argument true in constructor enables exceptions
						$mail->IsSMTP(); // telling the class to use SMTP
						$mail->Host	   = $CANON_MAIL_SMTP_HOST; // SMTP server
						//$mail->SMTPDebug  = 1;
						$mail->SMTPAuth   = true;
						$mail->Port	   = $CANON_MAIL_SMTP_PORT;
						$mail->Username   = $CANON_MAIL_SMTP_AUTH_USERNAME;
						$mail->Password   = $CANON_MAIL_SMTP_AUTH_PASSWORD;
						$mail->From = $CANON_MAIL_FROM_ADDRESS;
						$mail->FromName = $CANON_MAIL_FROM_NAME;
						$mail->SMTPSecure = $CANON_MAIL_SMTP_SECURE_TYPE;
						$mail->AddAddress($aemail);
						//$mail->ReplyTo = $CANON_MAIL_REPLYTO_ADDRESS;
						$mail->isHTML(false);
						$mail->Subject = "Welcome to $CANON_APP_NAME!";
						$mail->Body = "If you are trying to create an account, "
									."go to $CANON_WEB_HOST/canonical.php?token=".$randomString." "
									."and then scroll to the bottom and change your password!\n";
						$mail->AltBody = $mail->Body;
						$mail->send();
						echo '<FONT class="success-message">An email with password instructions is on the way!</FONT><BR>';
					} 
					catch (Exception $e) 
					{
						echo "<FONT class=\"error-message\">Mailer Error: " . $mail->ErrorInfo . ".  Use the Forgot password mechanism when it's fixed.</FONT><BR>\n";
					}
					$stmt = $pdo->prepare("INSERT INTO THROTTLE (TYPE, IP, EMAIL, EXPIRE) VALUES (:ty, :ip, '', :tm)");
					$stmt->execute(array(':ty' => CanonType::Account, ':ip' => $userip, ':tm' => (time()+86400)));
					
					skip_create:
				}
			}
		}
		else
		if ((isset($_POST['email']))
		&& ($_POST['email'] !='')
		&& (isset($_POST['password']))
		&& ($_POST['password'] !=''))
		{
			echo '<FONT class="error-message">Login failed.</FONT><BR>';
			$stmt = $pdo->prepare("INSERT INTO THROTTLE (TYPE, IP, EMAIL, EXPIRE) VALUES (:ty, :ip, :em, :tm)");
			$stmt->execute(array(':ty' => CanonType::Login, ':ip' => $userip, ':em' => $_POST['email'], ':tm' => (time()+86400)));
		}
			
		/******************************************
		begin all the editor commands
		******************************************/
		if(($privs > 0) && ($curname != ''))
		{
			if($command == 'NEWPIC')
			{
				$command = 'ADDPHOTO';
				$filesz = $_FILES['PHOTOPIC']['size'];
				if((!isset($filesz)) || ($filesz < 1))
					$showerror = '<FONT class="error-message">Pic upload failed!</FONT>';
				if(isset($filesz) && ($filesz > 1024*1024))
					$showerror = '<FONT class="error-message">Too Large! Less than 1megabyte please.</FONT>';
				if(isset($_FILES["PHOTOPIC"]["error"]) && ($_FILES["PHOTOPIC"]["error"] != 0))
					$showerror = '<FONT class="error-message">Upload error: '.$_FILES["PHOTOPIC"]["error"].'</FONT>';
				if (mtyp($_FILES['PHOTOPIC']['name']) == '')
					$showerror = '<FONT class="error-message">Bad Type.  Try jpg, gif, or png</FONT>';
				if ($showerror == '')
				{
					$stmt = $pdo->prepare("SELECT COUNT(*) C FROM THROTTLE WHERE TYPE=:ty AND EMAIL=:em AND EXPIRE > :tm");
					$stmt->execute(array(':ty' => CanonType::Image, ':em' => $aemail, ':tm' => time()));
					if(($row = $stmt->fetch())  && ($row["C"] >= TypeLimit::Forgot))
						echo '<FONT class="error-message">Too many images uploaded today.</FONT><BR>';
					else
					{
						$stmt = $pdo->prepare("INSERT INTO THROTTLE (TYPE, IP, EMAIL, EXPIRE) VALUES (:ty, :ip, :em, :tm)");
						$stmt->execute(array(':ty' => CanonType::Image, ':ip' => $userip, ':em' => $aemail, ':tm' => (time()+86400)));
						$command = '';
						$tmpname = $_FILES['PHOTOPIC']['tmp_name'];
						$name = $_FILES['PHOTOPIC']['name'];
						$desc = $_POST['DESCRIP'];
						$content = file_get_contents($tmpname);
						$istmt = $pdo->prepare("SELECT MAX(ORDINAL) M FROM PHOTOS WHERE MODEL=:md AND VARI=:va AND WIDTH=0");
						$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation));
						$maxord = 0;
						if($irow = $istmt->fetch())
							$maxord = $irow["M"] + 1;
						if(($maxord < 6)||($privs >1))
						{
							$istmt = $pdo->prepare("INSERT INTO PHOTOS (MODEL, VARI, APPROVED, DESCRIP, PHOTOURL, PHOTONAME, PHOTO, NAME, POSTDATE, ORDINAL, WIDTH) VALUES (:md, :va, 0, :de, '', :pn, :ph,:nm,:pd, :or, 0)");
							$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation, ':de' => $desc, ':pn' => $name, ':ph' => $content, ':nm' => $curname, ':pd' => time(), ':or' => $maxord));
							$command = 'ADDPHOTO';
							echo '<FONT class="success-message">Thanks! Your picture awaits approval.</FONT>';
							doLog('Added photo '.addslashes($name).' to variation '.addslashes($curvariation).' of model '.addslashes($curmodel));
						}
					}
				}
			}
		}
		if(($privs > 1) && ($curname != ''))
		{
			if(($command == 'VTOGGLE')
			&&($editflag==1))
			{
				$command = '';
				$stmt = $pdo->prepare("SELECT VERIFIED FROM ENTRY WHERE MODEL=:md AND VARI=:va AND PRODUCED=:pd");
				$stmt->execute(array(':md' => $curmodel, ':va' => $curvariation, ':pd' => $curprod));
				if($row = $stmt->fetch()) 
				{
					$newver = ($row["VERIFIED"] == 0) ? 1 : 0;
					$stmt = $pdo->prepare("UPDATE ENTRY SET VERIFIED=:vr WHERE MODEL=:md AND VARI=:va AND PRODUCED=:pd");
					$stmt->execute(array(':md' => $curmodel, ':va' => $curvariation, ':pd' => $curprod, ':vr' => $newver));
					doLog('Altered variation '.addslashes($curvariation).' of model to verified='.$newver);
				}
			}
			if(($command == 'MODPDESC')
			&&($editflag==1)
			&&(isset($_GET["photoname"]))
			&&(isset($_POST["DESCRIP"])))
			{
				$command = '';
				$pstmt = $pdo->prepare("UPDATE PHOTOS SET DESCRIP=:de, ORDINAL=:od WHERE MODEL=:md AND VARI=:va AND PHOTONAME=:pn AND WIDTH=0");
				$pstmt->execute(array(':md' =>$curmodel, ':va' => $curvariation, ':pn'=>$_GET["photoname"], ':de'=>$_POST["DESCRIP"], ':od'=>$_POST["NEWORD"]));
				doLog('Modified description of photo '.addslashes($_GET['photoname']).' in variation '.addslashes($curvariation).' of model '.addslashes($curmodel));
			}
			if(($command == 'APPPIC')
			&&(isset($_GET['photoname'])))
			{
				$command = '';
				$desc = $_GET['photoname'];
				$istmt = $pdo->prepare("UPDATE PHOTOS SET APPROVED=1 WHERE MODEL=:md AND VARI=:va AND PHOTONAME=:nm");
				$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation, ':nm' => $desc));
				doLog('Approved photo '.addslashes($desc).' in variation '.addslashes($curvariation).' of model '.addslashes($curmodel));
			}
			if(($command == 'DELPIC')
			&&(isset($_GET['photoname'])))
			{
				$command = '';
				$desc = $_GET['photoname'];
				$istmt = $pdo->prepare("DELETE FROM PHOTOS WHERE MODEL=:md AND VARI=:va AND PHOTONAME=:nm");
				$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation, ':nm' => $desc));
				doLog('Deleted photo '.addslashes($desc).' in variation '.addslashes($curvariation).' of model '.addslashes($curmodel));
			}
			if(($command == 'MODMODEL')
			&&($editflag == 1))
			{
				$newmodel = trim($_POST["DESCRIP"]);
				if(strlen($newmodel)>13)
					$newmodel=substr($newmodel, 0, 13);
				$newcat = trim($_POST["NEWCAT"]);
				$tmpx = strpos($newcat, '_');
				$produced = substr($newcat, 0, $tmpx);
				$newcat = substr($newcat,$tmpx+1);
				$purge = isset($_POST["DELALL"]) ? $_POST["DELALL"] : '';
				$istmt=$pdo->prepare("SELECT VARI FROM ENTRY WHERE MODEL=:md AND CATNAME=:ca AND PRODUCED=:pd");
				$istmt->execute(array(':md' => $curmodel, ':ca' => $curcat, ':pd' => $curprod));
				$varies = array();
				$command = 'EDITMODEL';
				while($irow = $istmt->fetch())
					array_push($varies, (int)$irow["VARI"]);
				if($purge == 'on')
				{
					$command = '';
					foreach($varies as $v)
					{
						$istmt=$pdo->prepare("DELETE FROM PHOTOS WHERE MODEL=:md AND VARI=:va");
						$istmt->execute(array(':md' => $curmodel, ':va' => $v));
						$istmt=$pdo->prepare("DELETE FROM OWNERS WHERE MODEL=:md AND VARI=:va");
						$istmt->execute(array(':md' => $curmodel, ':va' => $v));
						$istmt=$pdo->prepare("DELETE FROM NOTE WHERE HEADER=:ha");
						$istmt->execute(array(':ha' => $curmodel."_".$curprod));
						$istmt=$pdo->prepare("DELETE FROM ENTRY WHERE MODEL=:md AND VARI=:va");
						$istmt->execute(array(':md' => $curmodel, ':va' => $v));
						$istmt=$pdo->prepare("UPDATE ENTRY SET ALIASOF=NULL WHERE ALIASOF=:md AND VARI=:va");
						$istmt->execute(array(':md' => $curmodel, ':va' => $v));
					}
					doLog('Deleted model '.addslashes($curmodel));
					unset($curmodel);
					unset($curvariation);
					updateDate();
				}
				else
				{
					$command = 'EDITMODEL';
					if ($newmodel == '')
						$showerror = '<FONT class="error-message">Model cant be empty!</FONT>';
					else
					if($newmodel != $curmodel)
					{
						$istmt=$pdo->prepare("SELECT MODEL FROM ENTRY WHERE MODEL=:md AND PRODUCED=:pd");
						$istmt->execute(array(':md' => $newmodel, ':pd' => $curprod));
						if($irow = $istmt->fetch())
							$showerror = '<FONT class="error-message">New Model Name already exists!</FONT>';
					}
					if($newcat != $curcat)
					{
						$istmt=$pdo->prepare("SELECT 1 FROM CAT WHERE CATNAME=:nm");
						$istmt->execute(array(':nm' => $newcat));
						if(!($irow = $istmt->fetch()))
							$showerror = '<FONT class="error-message">New category "'.htmlspecialchars($newcat, ENT_QUOTES).'" doesnt exist!</FONT>';
					}
					if ($showerror == '')
					{
						foreach($varies as $v)
						{
							if($newmodel != $curmodel)
							{
								$istmt=$pdo->prepare("UPDATE PHOTOS SET MODEL=:nm WHERE MODEL=:md AND VARI=:va");
								$istmt->execute(array(':md' => $curmodel, ':va' => $v, ':nm' => $newmodel));
								$istmt=$pdo->prepare("UPDATE OWNERS SET MODEL=:nm WHERE MODEL=:md AND VARI=:va");
								$istmt->execute(array(':md' => $curmodel, ':va' => $v, ':nm' => $newmodel));
								$istmt=$pdo->prepare("UPDATE NOTE SET HEADER=:nh WHERE HEADER=:ha");
								$istmt->execute(array(':ha' => $curmodel."_".$curprod, ':nh' => $newmodel."_".$curprod));
							}
							$istmt=$pdo->prepare("UPDATE ENTRY SET MODEL=:nm, CATNAME=:nc, PRODUCED=:pd WHERE MODEL=:md AND VARI=:va");
							$istmt->execute(array(':md' => $curmodel,
												  ':va' => $v,
												  ':nm' => $newmodel,
												  ':nc' => $newcat,
												  ':pd' => $produced));
						}
						if($newmodel != $curmodel)
						{
							$istmt=$pdo->prepare("UPDATE ENTRY SET ALIASOF=:na WHERE ALIASOF=:md and PRODUCED=:pd");
							$istmt->execute(array(':na' => $newmodel, ':md' => $curmodel, ':pd' => $produced));
						}
						doLog('Modified model '.addslashes($curmodel).': new='.addslashes($newmodel).' in cat='.addslashes($newcat));
						$curmodel=$newmodel;
						$curcat=$newcat;
						$curprod=$produced;
						$command = '';
						$urlenc = setUrlEnc();
						updateDate();
					}
				}
			}
			if(($command == 'MODVARI')
			&&($editflag == 1))
			{
				$newblurb = str_replace("\n"," ",trim($_POST["DESCRIP"]));
				$purge = isset($_POST["DELALL"]) ? $_POST["DELALL"] : '';
				$move = isset($_POST["MOVE"]) ? $_POST["MOVE"] : '';
				$newaliasof = isset($_POST["ALIASOF"]) ? trim($_POST["ALIASOF"]) : NULL;
				$newaliasofv = isset($_POST["ALIASOFV"]) ? (int)trim($_POST["ALIASOFV"]) : 0;
				if($purge == 'on')
				{
					$command = '';
					$istmt=$pdo->prepare("DELETE FROM PHOTOS WHERE MODEL=:md AND VARI=:va");
					$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation));
					$istmt=$pdo->prepare("DELETE FROM OWNERS WHERE MODEL=:md AND VARI=:va");
					$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation));
					$istmt=$pdo->prepare("DELETE FROM ENTRY WHERE MODEL=:md AND VARI=:va");
					$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation));
					$istmt=$pdo->prepare("UPDATE ENTRY SET ALIASOF=NULL WHERE ALIASOF=:md AND ALIASOFV=:va");
					$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation));
					$istmt=$pdo->prepare("SELECT COUNT(*) C FROM ENTRY WHERE MODEL=:md");
					$istmt->execute(array(':md' => $curmodel));
					$numct = ($irow = $istmt->fetch()) ? (int)$irow["C"] : 0;
					if($numct == 1)
					{
						$istmt=$pdo->prepare("UPDATE PHOTOS SET VARI=0 WHERE MODEL=:md");
						$istmt->execute(array(':md' => $curmodel));
						$istmt=$pdo->prepare("UPDATE OWNERS SET VARI=0 WHERE MODEL=:md");
						$istmt->execute(array(':md' => $curmodel));
						$istmt=$pdo->prepare("UPDATE ENTRY SET VARI=0 WHERE MODEL=:md");
						$istmt->execute(array(':md' => $curmodel));
						$istmt=$pdo->prepare("UPDATE ENTRY SET ALIASOFV=0 WHERE ALIASOF=:md");
						$istmt->execute(array(':md' => $curmodel));
					}
					else
					{
						$istmt=$pdo->prepare("UPDATE PHOTOS SET VARI=VARI-1 WHERE MODEL=:md AND VARI>:va");
						$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation));
						$istmt=$pdo->prepare("UPDATE OWNERS SET VARI=VARI-1 WHERE MODEL=:md AND VARI>:va");
						$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation));
						$istmt=$pdo->prepare("UPDATE ENTRY SET VARI=VARI-1 WHERE MODEL=:md AND VARI>:va");
						$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation));
						$istmt=$pdo->prepare("UPDATE ENTRY SET ALIASOFV=ALIASOFV-1 WHERE ALIASOF=:md AND ALIASOFV>:va");
						$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation));
					}
					doLog('Deleted variation '.addslashes($curvariation).' of model '.addslashes($curmodel));
					unset($curmodel);
					unset($curvariation);
					updateDate();
				}
				else
				{
					$command = ($move == 'on') ? 'MOVEVARI' : 'EDITVARI';
					if($move == 'on')
					{
						$newmodel = trim($newblurb);
						if(strlen($newmodel)>13)
							$newmodel = substr($newmodel,0,13);
						$newcat = isset($_POST["NEWCAT"]) ? trim($_POST["NEWCAT"]) : '';
						$tmpx = strpos($newcat, '_');
						$produced = substr($newcat,0,$tmpx);
						$newcat = substr($newcat,$tmpx+1);
						if($newcat != $curcat)
						{
							$istmt=$pdo->prepare("SELECT 1 FROM CAT WHERE CATNAME=:nm");
							$istmt->execute(array(':nm' => $newcat));
							if(!($irow = $istmt->fetch()))
								$showerror = '<FONT class="error-message">New category "'.htmlspecialchars($newcat, ENT_QUOTES).'" doesnt exist!</FONT>';
						}
						if ($showerror == '')
						{
							if (($newmodel != $curmodel)
							   ||($newcat != $curcat)
							   ||($produced != $curprod))
							{
								if($newmodel == $curmodel)
								{
									// model unchanged, but category moved:  gain prod of new category
									// changed to a cat, model unchanged, but doesn't exist, so vari also 0
									$istmt=$pdo->prepare("UPDATE ENTRY SET CATNAME=:cn, PRODUCED=:pd WHERE MODEL=:om AND VARI=:va");
									$istmt->execute(array(':om' => $curmodel,
														  ':va' => $curvariation,
														  ':cn' => $newcat,
														  ':pd' => $produced));
									doLog('Moved variation '.addslashes($curvariation).' of model '.addslashes($curmodel).' to cat='.addslashes($newcat));
								}
								else
								{
									$newvari = 0;
									$istmt=$pdo->prepare("SELECT MAX(VARI) V FROM ENTRY WHERE MODEL=:nm");
									$istmt->execute(array(':nm' => $newmodel));
									if($irow = $istmt->fetch())
									{
										$newvari = $irow["V"] + 1;
										if($newvari == 1)
										{
											$istmt=$pdo->prepare("UPDATE ENTRY SET VARI=1 WHERE MODEL=:nm AND VARI=0");
											$istmt->execute(array(':nm' => $newmodel));
											$istmt=$pdo->prepare("UPDATE PHOTOS SET VARI=1 WHERE MODEL=:nm AND VARI=0");
											$istmt->execute(array(':nm' => $newmodel));
											$istmt=$pdo->prepare("UPDATE OWNERS SET VARI=1 WHERE MODEL=:nm AND VARI=0");
											$istmt->execute(array(':nm' => $newmodel));
										}
									}
									$istmt=$pdo->prepare("UPDATE ENTRY SET MODEL=:nm, CATNAME=:nc, PRODUCED=:np, VARI=:nv WHERE MODEL=:om AND VARI=:ov");
									$istmt->execute(array(':om' => $curmodel,
														  ':ov' => $curvariation,
														  ':nm' => $newmodel,
														  ':nc' => $newcat,
														  ':np' => $produced,
														  ':nv' => $newvari));
									$istmt=$pdo->prepare("UPDATE PHOTOS SET MODEL=:nm, VARI=:nv WHERE MODEL=:om AND VARI=:ov");
									$istmt->execute(array(':om' => $curmodel,':ov' => $curvariation,':nm' => $newmodel,':nv' => $newvari));
									$istmt=$pdo->prepare("UPDATE OWNERS SET MODEL=:nm, VARI=:nv WHERE MODEL=:om AND VARI=:ov");
									$istmt->execute(array(':om' => $curmodel,':ov' => $curvariation,':nm' => $newmodel,':nv' => $newvari));
									// now to re-vari the old position
									$istmt=$pdo->prepare("SELECT COUNT(*) C FROM ENTRY WHERE MODEL=:md");
									$istmt->execute(array(':md' => $curmodel));
									$numct = ($irow = $istmt->fetch()) ? $irow["C"] : 0;
									if($numct == 1)
									{
										$istmt=$pdo->prepare("UPDATE ENTRY SET VARI=0 WHERE MODEL=:md");
										$istmt->execute(array(':md' => $curmodel));
										$istmt=$pdo->prepare("UPDATE PHOTOS SET VARI=0 WHERE MODEL=:md");
										$istmt->execute(array(':md' => $curmodel));
										$istmt=$pdo->prepare("UPDATE OWNERS SET VARI=0 WHERE MODEL=:md");
										$istmt->execute(array(':md' => $curmodel));
									}
									else
									{
										$istmt=$pdo->prepare("UPDATE PHOTOS SET VARI=VARI-1 WHERE MODEL=:md AND VARI>:va");
										$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation));
										$istmt=$pdo->prepare("UPDATE OWNERS SET VARI=VARI-1 WHERE MODEL=:md AND VARI>:va");
										$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation));
										$istmt=$pdo->prepare("UPDATE ENTRY SET VARI=VARI-1 WHERE MODEL=:md AND VARI>:va");
										$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation));
									}
									doLog('Moved variation '.addslashes($curvariation).' of model '.addslashes($curmodel).' to variation '.addslashes($newvari).' of model '.addslashes($newmodel).' in cat='.addslashes($newcat));
								}
							}
							else
							{
									$istmt=$pdo->prepare("SELECT COUNT(*) C FROM ENTRY WHERE MODEL=:md");
									$istmt->execute(array(':md' => $curmodel));
									$numct = ($irow = $istmt->fetch()) ? $irow["C"] : 0;
									if($numct == 1)
									{
										$istmt=$pdo->prepare("UPDATE ENTRY SET VARI=0 WHERE MODEL=:md");
										$istmt->execute(array(':md' => $curmodel));
										$istmt=$pdo->prepare("UPDATE PHOTOS SET VARI=0 WHERE MODEL=:md");
										$istmt->execute(array(':md' => $curmodel));
										$istmt=$pdo->prepare("UPDATE OWNERS SET VARI=0 WHERE MODEL=:md");
										$istmt->execute(array(':md' => $curmodel));
									}
							}
							$curmodel = $newmodel;
							$curcat=$newcat;
							$curprod=$produced;
							unset($curvariation);
							$command = '';
							updateDate();
							$urlenc = setUrlEnc();
						}
					}
					else
					{
						if ($newblurb == '')
							$showerror = '<FONT class="error-message">Description cant be empty!</FONT>';
						if(($newaliasof != null) && (strlen($newaliasof)>0))
						{
							$istmt=$pdo->prepare("SELECT 1 FROM ENTRY WHERE MODEL=:md AND VARI=:va");
							$istmt->execute(array(':md' => $newaliasof, ':va' => $newaliasofv));
							if(!($irow = $istmt->fetch()))
								$showerror = '<FONT class="error-message">Alias model "'.$newaliasof.'#'.$newaliasofv.'" doesnt exist!</FONT>';
						}
						if ($showerror == '')
						{
							$istmt=$pdo->prepare("UPDATE ENTRY SET SHORTBLURB=:nm, ALIASOF=:al, ALIASOFV=:av WHERE MODEL=:md AND VARI=:va");
							$istmt->execute(array(':md' => $curmodel,
												  ':va' => $curvariation,
												  ':al' => $newaliasof,
												  ':av' => $newaliasofv,
												  ':nm' => $newblurb));
							$command = '';
							updateDate();
							doLog('Modified description of variation '.addslashes($curvariation).' of model '.addslashes($curmodel));
						}
					}
				}
			}

			if(($command == 'NEWMODEL')
			&&($editflag == 1))
			{
				$newmodel = trim($_POST["MODELNAME"]);
				if(strlen($newmodel)>13)
					$newmodel=substr($newmodel, 0, 13);
				$descrip = trim($_POST["DESCRIP"]);
				if(strlen($descrip)>60)
					$descrip=substr($descrip, 0, 60);
				$command = 'ADDMODEL';
				if ($newmodel == '')
					$showerror = '<FONT class="error-message">Model cant be empty!</FONT>';
				else
				if ($descrip == '')
					$showerror = '<FONT class="error-message">Description cant be empty!</FONT>';
				else
				{
					$istmt=$pdo->prepare("SELECT MODEL FROM ENTRY WHERE MODEL=:md AND PRODUCED=:pd");
					$istmt->execute(array(':md' => $newmodel, ':pd' => $curprod));
					if($irow = $istmt->fetch())
						$showerror = '<FONT class="error-message">New Model Name already exists!</FONT>';
				}
				if($showerror == '')
				{
					$istmt=$pdo->prepare("INSERT INTO ENTRY (MODEL, VARI, CATNAME, PRODUCED, VERIFIED, SHORTBLURB)
										  VALUES (:mb, 0, :nm, :pd, 0, :ds)");
					$istmt->execute(array(':mb' => $newmodel, ':nm' => $curcat, ':pd' => $curprod, ':ds' => $descrip));
					$command = '';
					doLog('Added model '.addslashes($newmodel).' to cat='.addslashes($curcat));
					updateDate();
				}
			}

			if(($command == 'NEWVARIATION')
			&&($editflag == 1))
			{
				$descrip = trim($_POST["DESCRIP"]);
				$command = 'ADDVARIATION';
				$vari = 0;
				if ($descrip == '')
					$showerror = '<FONT class="error-message">Description cant be empty!</FONT>';
				else
				{
					$istmt=$pdo->prepare("SELECT MAX(VARI) M FROM ENTRY WHERE MODEL=:md AND PRODUCED=:pd");
					$istmt->execute(array(':md' => $curmodel, ':pd' => $curprod));
					if($irow = $istmt->fetch())
						$vari = $irow["M"];
				}
				if($showerror == '')
				{
					if($vari == 0)
					{
						$istmt=$pdo->prepare("UPDATE ENTRY SET VARI=1 WHERE MODEL=:md AND PRODUCED=:pd");
						$istmt->execute(array(':md' => $curmodel, ':pd' => $curprod));
						$istmt=$pdo->prepare("UPDATE PHOTOS SET VARI=1 WHERE MODEL=:md AND VARI=0");
						$istmt->execute(array(':md' => $curmodel));
						$istmt=$pdo->prepare("UPDATE OWNERS SET VARI=1 WHERE MODEL=:md AND VARI=0");
						$istmt->execute(array(':md' => $curmodel));
						$vari = 2;
					}
					else
						$vari = $vari + 1;
					$istmt=$pdo->prepare("INSERT INTO ENTRY (MODEL, VARI, CATNAME, PRODUCED, VERIFIED, SHORTBLURB)
										  VALUES (:mb, :vr, :nm, :pd, 0, :ds)");
					$istmt->execute(array(':mb' => $curmodel, ':vr'=>$vari, ':nm' => $curcat, ':pd' => $curprod, ':ds' => $descrip));
					$command = '';
					updateDate();
					doLog('Added new variation '.addslashes($vari).' to model '.addslashes($curmodel).' in cat='.addslashes($curcat));
				}
			}

			if(($command == 'NEWCAT')
			&&($editflag == 1))
			{
				$command = 'MODCAT';
				$curcat = makeToken();
				$curprod = ($_POST["PRODUCED"] == "on") ? 1 : 0;
				$istmt = $pdo->query("SELECT MAX(ORDINAL) M FROM CAT WHERE PRODUCED=".$curprod);
				$ord = "!";
				if($irow = $istmt->fetch())
					$ord = $irow["M"];
				$istmt=$pdo->prepare("INSERT INTO CAT (CATNAME, PRODUCED, NOTE, ORDINAL) VALUES (:nm, :pd, '', :or)");
				$istmt->execute(array(':nm' => $curcat, ':pd' => $curprod, ':or' => $ord));
				updateDate();
				doLog('Added new category:');
			}

			if(($command == 'MODCAT')
			&&($editflag == 1))
			{
				$newcat = trim($_POST["DESCRIP"]);
				$newprod = ($_POST["PRODUCED"] == "on") ? 1 : 0;
				$purge = isset($_POST["DELALL"]) ? $_POST["DELALL"] : '';
				$neword = (int)$_POST["NEWORD"];
				$istmt = $pdo->prepare("SELECT ORDINAL FROM CAT WHERE CATNAME=:nm AND PRODUCED=:pd");
				$istmt->execute(array(':nm' =>$curcat, ':pd' => $curprod));
				$ord = 0;
				if($irow = $istmt->fetch())
					$ord = (int)$irow["ORDINAL"];
				if($purge == 'on')
				{
					$command = '';
					$istmt=$pdo->prepare("SELECT COUNT(*) C FROM ENTRY WHERE CATNAME=:nm AND PRODUCED=:pd");
					$istmt->execute(array(':nm' => $curcat, ':pd' => $curprod));
					$numct = ($irow = $istmt->fetch()) ? (int)$irow["C"] : 0;
					if($numct == 0)
					{
						$istmt=$pdo->prepare("DELETE FROM CAT WHERE CATNAME=:nm AND PRODUCED=:pd");
						$istmt->execute(array(':nm' => $curcat, ':pd' => $curprod));
						$istmt = $pdo->prepare("UPDATE CAT SET ORDINAL=ORDINAL-1 WHERE ORDINAL > :ol");
						$istmt->execute(array(':ol' => $ord));
						doLog('Deleted category '.addslashes($curcat));
						unset($curcat);
						unset($curprod);
						unset($curmodel);
						unset($curvariation);
						$command = '';
						updateDate();
					}
					else
						$showerror='<FONT class="error-message">Something failed.</FONT>';
				}
				else
				{
					$command = 'EDITCAT';
					if ($newcat == '')
						$showerror = '<FONT class="error-message">Category name cant be empty!</FONT>';
					if($newcat != $curcat)
					{
						$istmt=$pdo->prepare("SELECT 1 FROM CAT WHERE CATNAME=:nm AND PRODUCED=:pd");
						$istmt->execute(array(':nm' => $newcat, ':pd' => $newprod));
						if($irow = $istmt->fetch())
							$showerror = '<FONT class="error-message">New category name "'.$newcat.'" already exists!</FONT>';
					}
					if($ord != $neword)
					{
						// first step is to remove it from ordinal list entirely
						$istmt = $pdo->prepare("UPDATE CAT SET ORDINAL=0 WHERE CATNAME=:nm AND PRODUCED=:pd");
						$istmt->execute(array(':nm' =>$curcat, ':pd' => $curprod));
						$istmt = $pdo->prepare("UPDATE CAT SET ORDINAL=ORDINAL-1 WHERE ORDINAL > :ol");
						$istmt->execute(array(':ol' => $ord));
						// now make room
						$istmt = $pdo->prepare("UPDATE CAT SET ORDINAL=ORDINAL+1 WHERE ORDINAL >= :ol");
						$istmt->execute(array(':ol' => $neword));
						// and finally, just set it
						$istmt = $pdo->prepare("UPDATE CAT SET ORDINAL=:od WHERE CATNAME=:nm AND PRODUCED=:pd");
						$istmt->execute(array(':nm' =>$curcat, ':pd' => $curprod, ':od' => $neword));

					}
					if ($showerror == '')
					{
						$istmt=$pdo->prepare("UPDATE CAT SET CATNAME=:nc, PRODUCED=:np WHERE CATNAME=:nm AND PRODUCED=:pd");
						$istmt->execute(array(':nm' => $curcat,
											  ':pd' => $curprod,
											  ':nc' => $newcat,
											  ':np' => $newprod));
						$istmt=$pdo->prepare("UPDATE ENTRY SET CATNAME=:nc, PRODUCED=:np WHERE CATNAME=:nm AND PRODUCED=:pd");
						$istmt->execute(array(':nm' => $curcat,
											  ':pd' => $curprod,
											  ':nc' => $newcat,
											  ':np' => $newprod));
						doLog('Modified category '.addslashes($curcat).' to '.addslashes($newcat));
						$curcat=$newcat;
						$curprod=$newprod;
						$command = '';
						updateDate();
					}
				}
			}
			if(($command == 'MODNOTE')
			&&($editflag == 1))
			{
				$newnote = str_replace("\n"," ",trim($_POST["DESCRIP"]));
				$usetab = $_POST["USETABLE"];
				if ($usetab ==  'NOTE')
				{
					$istmt = $pdo->prepare("UPDATE NOTE SET DESCRIP=:de WHERE HEADER=:nm");
					$istmt->execute(array(':nm' =>$curcat."_".$curprod, ':de' => $newnote));
				}
				else
				{
					$istmt = $pdo->prepare("UPDATE CAT SET NOTE=:de WHERE CATNAME=:nm AND PRODUCED=:pd");
					$istmt->execute(array(':nm' =>$curcat, ':pd' => $curprod, ':de' => $newnote));
				}
				$command = '';
				doLog('Modified description of category '.addslashes($curcat));
				updateDate();
			}
		}
	?>
	<FORM NAME="formication" ACTION="?<?php echo $urlenc; ?>" METHOD=POST>
		<INPUT TYPE=HIDDEN NAME=csrf_token VALUE="<?php echo $_SESSION['csrf_token']; ?>">
		<?php
		if((!isset($search))||($search == NULL)||(trim($search)==''))
			echo '<INPUT TYPE=TEXT NAME="SEARCH" SIZE=10><INPUT TYPE=SUBMIT VALUE="Search">'."\n";
		else
			echo '<INPUT TYPE=HIDDEN NAME="SEARCH" VALUE=" "><INPUT TYPE=SUBMIT VALUE="Show All">'."\n";
		?>
	</FORM>
	
	<?php
		$firstmodelthiscat = '';
		foreach(array(1, 0) as $p):
			if($p == 1)
				echo '<h3 class="group1-header">Produced in Some Quantities';
			else
				echo '<P><h3 class="group2-header">Products Not Released';
			if($editflag==1)
			{
				if(($command == 'ADDCAT')&&($privs > 1)&&($curprod==$p))
				{
					$stmt = $pdo->query("SELECT MAX(ORDINAL) M FROM CAT WHERE PRODUCED=".$p);
					$ord = "!";
					if($row = $stmt->fetch())
						$ord = $row["M"];
					echo '<FORM NAME=ADDCATTER METHOD=POST ENCTYPE="multipart/form-data" ACTION="?'.$urlenc.'&command=NEWCAT">';
					echo '<INPUT TYPE=HIDDEN NAME=csrf_token VALUE="' . $_SESSION['csrf_token'] . '">';
					echo '<INPUT TYPE=TEXT NAME=DESCRIP VALUE="New Category" SIZE=30>';
					echo '<BR><FONT class="model-link">Ord:<INPUT TYPE=TEXT SIZE=3 NAME=NEWORD VALUE="'.$ord.'"></FONT>';
					echo '<INPUT TYPE=HIDDEN NAME=PRODUCED VALUE="'.($p==1?'on':'').'">';
					echo '<INPUT TYPE=HIDDEN NAME=DELALL VALUE="">';
					echo '<INPUT TYPE=SUBMIT>';
					echo '</FORM>';
				}
				else
					echo '<a href="?command=ADDCAT&'.$urlenc.'&prod='.$p.'"><FONT class="edit-link">(+cat)</FONT></a>';
			}
	?>
	</h3></P>
	<FONT class="<?php echo ($p == 1) ? 'primary-category' : 'secondary-category'; ?>">
	<?php
		$sql = "SELECT CATNAME FROM CAT WHERE PRODUCED=".$p." ORDER BY ORDINAL";
		$issearch = (isset($search)&& ($search != ''));
		if ($issearch)
		{
			$srch = addslashes($search);
			if(($privs > 1) && (strtolower($srch) == "unapproved"))
				$sql = "select DISTINCT(CATNAME) FROM ENTRY WHERE PRODUCED=".$p." AND (MODEL IN (SELECT MODEL FROM PHOTOS WHERE APPROVED=0) or MODEL IN (SELECT MODEL FROM OWNERS WHERE APPROVED=0))";
			else
			if(strtolower($srch) == "pictured")
				$sql = "select DISTINCT(CATNAME) FROM ENTRY E WHERE PRODUCED=".$p." AND EXISTS (SELECT MODEL from PHOTOS P WHERE E.MODEL=P.MODEL and E.VARI=P.VARI)";
			else
			if(strtolower($srch) == "unpictured")
			{
				$sql = "select DISTINCT(CATNAME) FROM ENTRY E WHERE ALIASOF IS NULL AND PRODUCED=".$p." AND NOT EXISTS (SELECT MODEL from PHOTOS P WHERE E.MODEL=P.MODEL and E.VARI=P.VARI)";
			}
			else
			if((strtolower($srch) == "iown") && isset($curname) && ($curname != ''))
				$sql = "select DISTINCT(CATNAME) FROM ENTRY WHERE PRODUCED=".$p." AND (MODEL IN (SELECT MODEL FROM OWNERS WHERE NAME ='".addslashes($curname)."'))";
			else
				$sql = "SELECT DISTINCT(C.CATNAME), C.ORDINAL FROM CAT C INNER JOIN ENTRY E ON C.CATNAME = E.CATNAME WHERE C.PRODUCED=".$p." AND (E.MODEL LIKE '%".$srch."%' OR E.SHORTBLURB LIKE '%".$srch."%') ORDER BY C.ORDINAL";
		}
		$stmt = $pdo->query($sql);
		$ilastmodel = '';
		$ilastcat = '';
		$isnext = $command == 'NEXT';
		$isprev = $command == 'PREV';
		while($row = $stmt->fetch()) 
		{
			$icat = $row['CATNAME'];
			if($isnext && ($curcat == $ilastcat) && ($curprod == $p))
			{
				$curcat = $icat;
				$ilastcat = $icat;
				$ilastmodel = '';
				$curmodel = '';
			}
			echo '<A HREF="?UPTOP&prod='.$p.'&cat='.urlencode($icat).$urladd.'">';
			if ((isset($curcat)&&($curcat == $icat)&&($curprod==$p))||$issearch)
			{
						echo '<FONT class="selected-category">'.htmlentities($icat)."</FONT></a><br>\n";
				echo '<UL>'."\n";
				$sql = "SELECT MODEL,VARI FROM (SELECT MODEL,COUNT(VARI) AS VARI FROM ENTRY WHERE CATNAME=:nm AND PRODUCED=".$p." GROUP BY MODEL) AS T ORDER BY MODEL ";
				if ($issearch)
				{
					$srch = addslashes($search);
					if(($privs > 1) && (strtolower($srch) == "unapproved"))
						$sql = "SELECT MODEL,VARI FROM ENTRY WHERE PRODUCED=".$p." AND CATNAME=:nm AND (MODEL IN (SELECT MODEL FROM PHOTOS WHERE APPROVED=0) or MODEL IN (SELECT MODEL FROM OWNERS WHERE APPROVED=0))";
					else
					if(strtolower($srch) == "unpictured")
						$sql = "SELECT MODEL,VARI FROM ENTRY E WHERE ALIASOF IS NULL AND PRODUCED=".$p." AND CATNAME=:nm AND NOT EXISTS (SELECT MODEL from PHOTOS P WHERE E.MODEL=P.MODEL and E.VARI=P.VARI)";
					else
					if(strtolower($srch) == "pictured")
						$sql = "SELECT MODEL,VARI FROM ENTRY E WHERE PRODUCED=".$p." AND CATNAME=:nm AND EXISTS (SELECT MODEL from PHOTOS P WHERE E.MODEL=P.MODEL and E.VARI=P.VARI)";
					else
					if((strtolower($srch) == "iown") && isset($curname) && ($curname != ''))
						$sql = "SELECT MODEL,VARI FROM ENTRY WHERE PRODUCED=".$p." AND CATNAME=:nm AND (MODEL IN (SELECT MODEL FROM OWNERS WHERE NAME='".addslashes($curname)."'))";
					else
						$sql = "SELECT MODEL,VARI FROM (SELECT MODEL,COUNT(VARI) AS VARI FROM ENTRY WHERE CATNAME=:nm AND PRODUCED=".$p."  AND (MODEL LIKE '%".$srch."%' OR SHORTBLURB LIKE '%".$srch."%') GROUP BY MODEL) AS T ORDER BY MODEL ";
				}
				$istmt = $pdo->prepare($sql);
				$istmt->execute(array(':nm' =>$icat));
				while ($irow = $istmt->fetch()) 
				{
					$imodel = $irow["MODEL"];
					if(($firstmodelthiscat=='') && ($curcat == $icat) && ($curprod == $p))
						$firstmodelthiscat = $imodel;
					if($isnext && ($curcat == $ilastcat) && ($curprod == $p) && ($curmodel == $ilastmodel))
					{
						$curcat = $icat;
						$curmodel = $imodel;
						$isnext = false;
						$command = '';
						$urlenc = setUrlEnc();
					}
					else
					if($isprev && ($curcat == $icat) && ($curprod == $p) && ($curmodel == $imodel) && ($ilastmodel != ''))
					{
						$curcat = $ilastcat;
						$curmodel = $ilastmodel;
						$isprev = false;
						$command = '';
						$urlenc = setUrlEnc();
					}
					if($ilastmodel != $imodel)
					{
						$firstmodel=false;
						echo '<LI>';
						echo '<A HREF="?UPTOP&cat='.urlencode($icat).'&prod='.$p.'&model='.urlencode($imodel).$urladd.'">';
						echo '<FONT class="model-link">'.htmlentities($imodel);
						if (($irow["VARI"] > 1)&&(!$issearch))
							echo '&nbsp;&nbsp;<FONT class="variations-count">('.$irow["VARI"].' Variations)</FONT>';
						echo "</FONT></a>\n";
					}
					$ilastmodel = $imodel;
					$ilastcat = $icat;
				}
				echo '</UL>'."\n";
			}
			else
				echo '<FONT class="'.($p == 1 ? 'primary-category' : 'secondary-category').'">'.htmlentities($icat)."</FONT></a><br>\n";
		}
		echo '</FONT>'."\n";
		endforeach;
	?>
	</FONT>
	<P><BR>
	<?php
		if (isset($getcookie) && ($getcookie != null) && ($getcookie != '')&& ($cookiecookie == null))
			echo '<FORM NAME="fogging" ACTION="?token='.$getcookie.'" METHOD=POST>';
		else
			echo '<FORM NAME="fogging" ACTION="canonical.php" METHOD=POST>';
		echo '<INPUT TYPE=HIDDEN NAME=csrf_token VALUE="' . $_SESSION['csrf_token'] . '">';
	?>
	<FONT class="primary-category">
	<TABLE WIDTH=100% BORDER=0>
	<?php
		if($curname == '')
		{
			if($command == 'NEWACCT')
			{
				echo '<TR><TD><a name="login"><FONT CLASS="field-label">Name:</FONT></a></TD><TD WIDTH=85%><INPUT TYPE=TEXT NAME="newname" SIZE=15></TD></TR>';
				echo '<TR><TD><FONT CLASS="field-label">Email:</FONT></TD><TD><INPUT TYPE=TEXT NAME="newemail" SIZE=15></TD></TR>';
				echo '<TR><TD><FONT CLASS="field-label">Private</FONT></TD>';
                echo '<TD COLSPAN=2><INPUT TYPE=CHECKBOX NAME="newprivate"><FONT CLASS="field-label">Be an unlisted owner</FONT></TD></TR>';
				echo '<TR><TD COLSPAN=2><INPUT TYPE=SUBMIT VALUE=Create></TD></TR>'."\n";
			}
			else
			{
				echo '<TR><TD><a name="login"><FONT CLASS="field-label">Email:</FONT></a></TD><TD WIDTH=85%><INPUT TYPE=TEXT NAME="email" SIZE=15></TD></TR>';
				echo '<TR><TD><FONT CLASS="field-label">Pass:</FONT></TD><TD><INPUT TYPE=PASSWORD NAME="password" SIZE=10></TD></TR>';
				echo '<TR><TD></TD><TD><INPUT TYPE=CHECKBOX NAME=forgot><FONT CLASS="field-label">Forgot</FONT>';
				echo '</TD></TR>';
				echo '<TR><TD COLSPAN=2><INPUT TYPE=SUBMIT VALUE=Login></TD></TR>'."\n";
				echo '<TR><TD COLSPAN=2><a href="?'.$urlenc.'&command=NEWACCT#login"><FONT class="primary-category">Create new account</a></TD></TR>'."\n";
			}
		}
		else
		{
			echo '<TR><TD COLSPAN=2><FONT CLASS="field-label">Change your password</FONT></TD></TR>';
			echo '<TR><TD><FONT CLASS="field-label">New Pass:</FONT></TD><TD WIDTH=85%><INPUT TYPE=PASSWORD NAME="newpassword" SIZE=10></TD></TR>';
			echo '<TR><TD COLSPAN=2><INPUT TYPE=SUBMIT VALUE=Change></TD></TR>'."\n";
		}
	?>
		</TABLE>
		</FONT>
	</FORM>
	</TD>
	<TD VALIGN=TOP>
	<?php
		/******************************************
		Begin displaying the main Entry pane
		******************************************/
		if(($privs > 1) && ($command == 'SHOWLOG'))
		{
			echo "<TABLE BORDER=1 WIDTH=100%>\n";
			echo '<TR><TD WIDTH=15%>NAME</TD><TD WIDTH=15%>TIME</TD><TD>MESSAGE</TD></TR>';
			$stmt = $pdo->query("SELECT NAME,EDATE,EVENT FROM VISLOG ORDER BY ORDINAL DESC");
			while($row = $stmt->fetch())
			{
				$n = $row["NAME"];
				$date = new DateTime();
				$date->setTimestamp($row["EDATE"]);
				$datef = date_format($date, 'm/d/y h:ia');
				$msg = $row["EVENT"];
				echo '<TR>';
				echo '<TD>'.$n.'</TD>';
				echo '<TD>'.$datef.'</TD>';
				echo '<TD>'.$msg.'</TD>';
				echo '</TR>'."\n";
			}
			echo "</TABLE>\n";
		}
		else
		if(isset($curcat))
		{
			echo "<TABLE BORDER=0 WIDTH=100%>\n";
			echo '<TR><TD COLSPAN=2 class="category-header">';
			echo '<FORM NAME=MODELMUNGE METHOD=POST ENCTYPE="multipart/form-data" ACTION="?'.$urlenc.'&command=MODCAT">';
			echo '<INPUT TYPE=HIDDEN NAME=csrf_token VALUE="' . $_SESSION['csrf_token'] . '">';
			echo '<FONT SIZE=5 class="category-header">';
			if($editflag == 1)
			{
				if($command == 'EDITCAT')
				{
					$stmt = $pdo->prepare("SELECT ORDINAL FROM CAT WHERE CATNAME=:nm AND PRODUCED=:pd");
					$stmt->execute(array(':nm' =>$curcat, ':pd' => $curprod));
					$ord = "!";
					if($row = $stmt->fetch())
						$ord = $row["ORDINAL"];
					echo '<FONT class="category-header"><INPUT TYPE=TEXT NAME=DESCRIP VALUE="'.htmlentities($curcat).'" SIZE=30>';
					echo '&nbsp;&nbsp;Ord:<INPUT TYPE=TEXT SIZE=3 NAME=NEWORD VALUE="'.$ord.'">';
					echo '<INPUT TYPE=CHECKBOX NAME="PRODUCED" '.($curprod==1?'CHECKED':'').'>Produced';
					echo '<BR><INPUT TYPE=SUBMIT>&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;&ensp;';
					$stmt = $pdo->prepare("SELECT COUNT(*) C FROM ENTRY WHERE CATNAME=:nm AND PRODUCED=:pd");
					$stmt->execute(array(':nm' =>$curcat, ':pd' => $curprod));
					if($row = $stmt->fetch())
					{
						$rowct = (int)$row["C"];
						if($rowct == 0)
							echo '<INPUT TYPE=CHECKBOX NAME=DELALL><FONT class="error-message"><B>DELETE THIS CATEGORY</B></FONT>';
					}
				}
				else
				{
					echo '<a href="?prod='.urlencode($curprod).'&cat='.urlencode($curcat).$urladd.'" STYLE="text-decoration-line: none;"><FONT class="category-header">';
					echo htmlentities($curcat);
					echo '</FONT></a>';
					echo '<a href="?command=EDITCAT&'.$urlenc.'"><FONT class="edit-link">(e)</FONT></a>';
				}
			}
			else
			{
				echo '<a href="?prod='.urlencode($curprod).'&cat='.urlencode($curcat).$urladd.'" STYLE="text-decoration-line: none;"><FONT class="category-header">';
				echo htmlentities($curcat);
				echo '</FONT></a>';
			}
			echo '</FONT></FORM></TD></TR>'."\n";
			echo '<TR><TD COLSPAN=2 class="note-section">';
			echo '<FORM NAME=NOTEMUNGE METHOD=POST ENCTYPE="multipart/form-data" ACTION="?'.$urlenc.'&command=MODNOTE">';
			echo '<INPUT TYPE=HIDDEN NAME=csrf_token VALUE="' . $_SESSION['csrf_token'] . '">';
			echo '<FONT SIZE=3 class="note-section">';
			$stmt = $pdo->prepare("SELECT DESCRIP FROM NOTE WHERE HEADER=:nm");
			$stmt->execute(array(':nm' =>$curcat."_".$curprod));
			$usetable = 'NOTE';
			$note = '';
			if($row = $stmt->fetch()) 
				$note = $row["DESCRIP"];
			else 
			{
				$stmt = $pdo->prepare("SELECT NOTE FROM CAT WHERE CATNAME=:nm AND PRODUCED=:pd");
				$stmt->execute(array(':nm' =>$curcat, ':pd' => $curprod));
				while($row = $stmt->fetch()) 
				{
					$usetable = 'CAT';
					$note = $row["NOTE"];
				}
			}

			if($editflag == 1)
			{
				if($command == 'EDITNOTE')
				{
					echo '<TEXTAREA NAME=DESCRIP ROWS=3 COLS=60>'.htmlentities($note).'</TEXTAREA><INPUT TYPE=SUBMIT>';
					echo '<INPUT TYPE=HIDDEN NAME=USETABLE VALUE="'.$usetable.'">';
				}
				else
				{
							echo htmlentities($note);
					echo '<a href="?command=EDITNOTE&'.$urlenc.'"><FONT class="edit-link">(e)</FONT></a>';
				}
			}
			else
						echo htmlentities($note);

			echo '</FONT></FORM>';
			echo '</TD></TR>'."\n";
			if(isset($curmodel))
			{
				echo '<TR><TD COLSPAN=2 class="model-section">';
				echo '<FORM NAME=MODELMUNGE METHOD=POST ENCTYPE="multipart/form-data" ACTION="?'.$urlenc.'&command=MODMODEL">';
				echo '<INPUT TYPE=HIDDEN NAME=csrf_token VALUE="' . $_SESSION['csrf_token'] . '">';
				if($editflag == 1)
				{
					if($command == 'EDITMODEL')
					{
						echo '<TABLE BORDER=0 CELLSPACING=0 CELLPADDING=0>';
						echo '<TR><TD WIDTH=50%>';
						echo '<FONT SIZE=3 class="model-section">Model: ';
						echo '<INPUT TYPE=TEXT NAME=DESCRIP VALUE="'.htmlentities($curmodel).'" SIZE=30>&nbsp;&nbsp;&nbsp;'.$showerror;
						echo '</FONT></TD><TD WIDTH=50%>';
						echo '<FONT SIZE=3 class="model-section">Category: <SELECT NAME=NEWCAT>>';
						$mstmt = $pdo->query("SELECT CATNAME,PRODUCED FROM CAT ORDER BY PRODUCED DESC, ORDINAL");
						while($mrow = $mstmt->fetch()) 
						{
							echo '<OPTION VALUE="'.$mrow["PRODUCED"].'_'.htmlentities($mrow["CATNAME"]).'" ';
							if(($mrow["CATNAME"] == $curcat)&&($mrow["PRODUCED"]==$curprod))
								echo 'SELECTED';
							echo '>';
							echo ($mrow["PRODUCED"] == 1) ? '(P)' : '(NP)';
							echo ' '.$mrow["CATNAME"];
						}
						echo '</SELECT>';
						echo '</FONT></TD></TR><TR><TD>';
						echo '<INPUT TYPE=SUBMIT>';
						echo '</TD><TD ALIGN=LEFT>';
						echo '<INPUT TYPE=CHECKBOX NAME=DELALL><FONT class="error-message"><B>DELETE THIS MODEL - ALL VARIATIONS - PHOTOS - ETC</B>';
						echo '</TD></TR></TABLE>';
					}
					else
					{
								echo '<FONT SIZE=4 class="model-section">Model: '.htmlentities($curmodel).'</FONT>';
						echo '<a href="?command=EDITMODEL&'.$urlenc.'"><FONT class="edit-link">(e)</FONT></a>';
					}
				}
				else
							echo '<FONT SIZE=4 class="model-section">Model: '.htmlentities($curmodel).'</FONT>';
				echo '</FONT></FORM>';
				echo '<P><TABLE BORDER=0 WIDTH=100%><TR><TD>';
				if($firstmodelthiscat != $curmodel)
					echo '<a href="?'.$urlenc.'&command=PREV"><FONT class="nav-link"><-Prev</FONT></a>';
				echo '</TD><TD ALIGN=RIGHT>';
				echo '<a href="?'.$urlenc.'&command=NEXT"><FONT class="nav-link">Next-></FONT></a>';
				echo '</TD></TR></TABLE>'."\n";
				echo '</TD></TR>'."\n";
				$stmt = $pdo->prepare("SELECT DESCRIP FROM NOTE WHERE HEADER=:nm");
				$stmt->execute(array(':nm' =>$curmodel."_".$curprod));
				if($row = $stmt->fetch())
				{
							echo '<TR><TD COLSPAN=2 class="note-section"><FONT SIZE=3 class="note-section">'.htmlentities($row["DESCRIP"]);
					echo '</FONT></TD></TR>'."\n";
				}
				$stmt = $pdo->prepare("SELECT * FROM ENTRY WHERE PRODUCED=:pd AND MODEL=:md AND CATNAME=:ca ORDER BY VARI");
				$stmt->execute(array(':pd' => $curprod, ':md' => $curmodel, ':ca' => $curcat));
				while ($row = $stmt->fetch()) 
				{
					$variation = $row["VARI"];
					$verified = $row["VERIFIED"];
					$aliasOf = $row["ALIASOF"];
					$aliasOfV = $row["ALIASOFV"];
					echo '<TR><TD COLSPAN=2 class="variation-section">';
					echo '<FORM NAME=VARIMUNGE METHOD=POST ENCTYPE="multipart/form-data" ACTION="?'.$urlenc.'&variation='.$variation.'&command=MODVARI">';
					echo '<INPUT TYPE=HIDDEN NAME=csrf_token VALUE="' . $_SESSION['csrf_token'] . '">';
					if($variation != 0)
						echo '<FONT SIZE=3 class="variation-section">Variation #'.$variation.': </FONT>';
					echo '<FONT class="variation-text">';
					if($editflag == 1)
					{
						if(($command == 'EDITVARI')&& ($variation == $curvariation))
						{
							echo '<FONT class="variation-section">';
							echo '<TEXTAREA NAME=DESCRIP ROWS=3 COLS=60>'.htmlentities($row["SHORTBLURB"]).'</TEXTAREA>';
							$istmt = $pdo->prepare("SELECT COUNT(*) C FROM ENTRY WHERE MODEL=:md");
							$istmt->execute(array(':md' =>$curmodel));
							$modct = ($irow = $istmt->fetch()) ? (int)$irow["C"] : 1;
							if($modct > 1)
								echo '<INPUT TYPE=CHECKBOX NAME=DELALL><FONT class="error-message"><B>DELETE THIS VARIATION & PHOTOS - ETC</B></FONT>';
							echo '<INPUT TYPE=HIDDEN NAME=MOVE VALUE="">';
							echo '<BR>Alias Of: ';
							if ($aliasOf == null)
								$aliasOf = '';
							if ($aliasOfV == null)
								$aliasOfV = '';
							echo '<INPUT TYPE=TEXT NAME=ALIASOF VALUE="'.htmlentities($aliasOf).'" SIZE=15>#<INPUT TYPE=TEXT NAME=ALIASOFV VALUE="'.htmlentities($aliasOfV).'" SIZE=3>'.$showerror;
							echo '<BR><INPUT TYPE=SUBMIT>';
						}
						else
						if(($command == 'MOVEVARI')&& ($variation == $curvariation))
						{
							echo '<FONT class="variation-section">';
							echo '<BR>New Model: <INPUT TYPE=TEXT NAME=DESCRIP SIZE=13 VALUE="'.htmlentities($curmodel).'">';
							echo '<BR>Category: <SELECT NAME=NEWCAT>>';
							$mstmt = $pdo->query("SELECT CATNAME,PRODUCED FROM CAT ORDER BY PRODUCED DESC, ORDINAL");
							while($mrow = $mstmt->fetch()) 
							{
								echo '<OPTION VALUE="'.$mrow["PRODUCED"].'_'.htmlentities($mrow["CATNAME"]).'" ';
								if(($mrow["CATNAME"] == $curcat)&&($mrow["PRODUCED"]==$curprod))
									echo 'SELECTED';
								echo '>';
								echo ($mrow["PRODUCED"] == 1) ? '(P)' : '(NP)';
								echo ' '.$mrow["CATNAME"];
							}
							echo '</SELECT>';
							echo '<INPUT TYPE=HIDDEN NAME=MOVE VALUE="on">';
							echo '<BR><INPUT TYPE=SUBMIT>';
						}
						else
						{
									echo atLinkFix($row["SHORTBLURB"]);
							echo '<a href="?command=EDITVARI&variation='.$variation.'&'.$urlenc.'"><FONT class="edit-link">(e)</FONT></a>';
							echo '&nbsp;&nbsp;&nbsp;<a href="?command=MOVEVARI&variation='.$variation.'&'.$urlenc.'"><FONT class="edit-link">(mv)</FONT></a>';
						}
					}
					else
								echo atLinkFix($row["SHORTBLURB"]);
					echo '</FONT></FORM></TD></TR>'."\n";
					if($aliasOf != NULL)
					{
						$astmt = $pdo->prepare("SELECT * FROM ENTRY WHERE MODEL=:md AND VARI=:va");
						$astmt->execute(array(':md' => $aliasOf, ':va' => $aliasOfV));
						if($arow = $astmt->fetch()) 
						{
							echo '<TR><TD COLSPAN=2 class="variation-section">';
							echo '<FONT class="variation-text">';
							echo atLinkFix('Alias of @'.str_replace(' ','_',$aliasOf).'#'.$aliasOfV.' <P>');
							echo atLinkFix($arow["SHORTBLURB"]);
							echo '</FONT></TD></TR>'."\n";
							$astmt = $pdo->prepare("SELECT PHOTOURL,PHOTONAME,DESCRIP,APPROVED,ORDINAL,NAME FROM PHOTOS WHERE MODEL=:md AND VARI=:va AND WIDTH=0 ORDER BY ORDINAL");
							$astmt->execute(array(':md' =>$aliasOf, ':va' => $aliasOfV));
							echo '<TR><TD>';
							while($arow = $astmt->fetch()) 
							{
								$pname = $arow["NAME"];
								if(($arow["APPROVED"] == 0) 
								&&($privs<2)
								&&(($pname == '')||($curname != $pname)))
									continue;
								if(($arow["PHOTOURL"] != null) && ($arow["PHOTOURL"] != ''))
								{
									echo '<BR><img src="'.$irow["PHOTOURL"].'" WIDTH=400>';
									if(($arow["DESCRIP"] != null) && ($arow["DESCRIP"] != ''))
										echo '<FONT class="photo-description">'.$arow["DESCRIP"].'</FONT>';
									echo "<P>\n";
								}
								if(($arow["PHOTONAME"] != null) && ($arow["PHOTONAME"] != ''))
								{
									$mimetype = mtyp($arow["PHOTONAME"]);
									echo '<a target="_blank" href="?'.$urlenc.'&model='.urlencode($aliasOf).'&command=IMG&variation='.$aliasOfV.'&iname='.urlencode($arow["PHOTONAME"]).'">';
									echo '<img align=left src="?'.$urlenc.'&model='.urlencode($aliasOf).'&command=IMG&variation='.$aliasOfV.'&iname='.urlencode($arow["PHOTONAME"]).'&width=small"/></a>';
									echo '<BR CLEAR=LEFT>';
								}
							}
							$asql = "SELECT OWNERS.NAME,SERIAL,PRIVATE,APPROVED FROM OWNERS INNER JOIN OWNER ON OWNER.NAME = OWNERS.NAME WHERE MODEL=:md AND VARI=:va";
							$astmt = $pdo->prepare($asql);
							$astmt->execute(array(':md' =>$aliasOf, ':va' => $aliasOfV));
							while($arow = $astmt->fetch()) 
							{
								$isowner = 0;
								if (($curname != '') && ($arow["NAME"] == $curname))
									$isowner = 1;
								if ($arow["APPROVED"] == 0)
								{
									if($isowner == 1)
										echo "<BR><FONT class=\"success-message\">Ownership registered and awaiting approval.</FONT>";
									if($privs < 2)
										continue;
								}
								if (($arow["PRIVATE"] == 0)||($privs > 1))
								{
									echo '<BR><FONT class="owner-text">Owner: '.$arow["NAME"];
									if(($arow["SERIAL"] != null) && ($arow["SERIAL"] != ''))
									{
										if($irow["PRIVATE"] == 0)
											echo ', serial#'.$arow["SERIAL"];
										else
											echo ', <FONT class="edit-link">serial#'.$irow["SERIAL"].'</FONT>';
									}
									echo '</FONT>'."\n";
								}
							}
							echo '</TD></TR>';
						}
					}
					echo '<TR><TD>';
					$istmt = $pdo->prepare("SELECT PHOTOURL,PHOTONAME,DESCRIP,APPROVED,ORDINAL,NAME FROM PHOTOS WHERE MODEL=:md AND VARI=:va AND WIDTH=0 ORDER BY ORDINAL");
					$istmt->execute(array(':md' =>$curmodel, ':va' => $variation));
					while($irow = $istmt->fetch()) 
					{
						$pname = $irow["NAME"];
						if(($irow["APPROVED"] == 0) 
						&&($privs<2)
						&&(($pname == '')||($curname != $pname)))
							continue;
						if(($irow["PHOTOURL"] != null) && ($irow["PHOTOURL"] != ''))
						{
							echo '<BR><img src="'.$irow["PHOTOURL"].'" WIDTH=400>';
							if(($irow["DESCRIP"] != null) && ($irow["DESCRIP"] != ''))
								echo '<FONT class="photo-description">'.$irow["DESCRIP"].'</FONT>';
							echo "<P>\n";
						}
						if(($irow["PHOTONAME"] != null) && ($irow["PHOTONAME"] != ''))
						{
							$mimetype = mtyp($irow["PHOTONAME"]);
							//echo '<BR><img width=400 src="data:'.$mimetype.';base64,'.base64_encode( $irow['PHOTO'] ).'"/>';
							if ($editflag == 1)
								echo '<a href="?'.$urlenc.'&variation='.$variation.'&command=DELPIC&photoname='.urldecode($irow["PHOTONAME"]).'"><FONT class="error-message">X</FONT></a>';
							echo '<a target="_blank" href="?'.$urlenc.'&command=IMG&variation='.$variation.'&iname='.urlencode($irow["PHOTONAME"]).'">';
							echo '<img align=left src="?'.$urlenc.'&command=IMG&variation='.$variation.'&iname='.urlencode($irow["PHOTONAME"]).'&width=small"/></a>';
							echo '<BR CLEAR=LEFT>';
							if(($command == 'PDESCRIP')
							&&($editflag==1)
							&&($curvariation==$variation)
							&&($_GET["photoname"] == $irow["PHOTONAME"]))
							{
								echo '<FORM NAME=PICDESCF METHOD=POST ENCTYPE="multipart/form-data" ACTION="?'.$urlenc.'&variation='.urlencode($variation).'&command=MODPDESC&photoname='.urldecode($irow["PHOTONAME"]).'">'.$showerror;
								echo '<INPUT TYPE=HIDDEN NAME=csrf_token VALUE="' . $_SESSION['csrf_token'] . '">';
								echo '<BR><FONT class="owner-text">Desc: <INPUT TYPE=TEXT NAME=DESCRIP VALUE="';
								if(($irow["DESCRIP"] != null) && ($irow["DESCRIP"] != ''))
									echo htmlentities($irow["DESCRIP"]);
								echo '" SIZE=80><BR>Ordinal: <INPUT TYPE=TEXT NAME=NEWORD VALUE="'.$irow["ORDINAL"].'"><BR>';
								echo '<INPUT TYPE=SUBMIT>';
								echo '</FORM>'."\n";
							}
							else
							if(($irow["DESCRIP"] != null) && ($irow["DESCRIP"] != ''))
								echo '<FONT class="photo-description">'.$irow["DESCRIP"].'</FONT>';
							if(($editflag == 1)&&($command != 'PDESCRIP'))
								echo '<a href="?command=PDESCRIP&'.$urlenc.'&variation='.$variation.'&photoname='.urldecode($irow["PHOTONAME"]).'"><FONT class="edit-link">(e)</FONT></a>';
							if (($irow["APPROVED"] == 0)&&($privs > 1)) 
							{
								echo '<a href="?'.$urlenc.'&variation='.$variation.'&command=APPPIC&photoname='.urldecode($irow["PHOTONAME"]).'"><FONT class="success-message">(approve)</FONT></a>';
								echo '<a href="?'.$urlenc.'&variation='.$variation.'&command=DELPIC&photoname='.urldecode($irow["PHOTONAME"]).'"><FONT class="error-message">(delete)</FONT></a>';
							}
							echo "<P>\n";
						}
					}
					if($privs > 0)
					{
						$istmt = $pdo->prepare("SELECT COUNT(*) C FROM PHOTOS WHERE MODEL=:md AND VARI=:va AND WIDTH=0");
						$istmt->execute(array(':md' =>$curmodel, ':va' => $variation));
						$photoct = ($irow = $istmt->fetch()) ? (int)$irow["C"] : 0;
						if(($privs > 1) || ($photoct < 5))
						{
							if(($command == 'ADDPIC') && ($curvariation == $variation))
							{
								echo '<FORM NAME=PICRAL METHOD=POST ENCTYPE="multipart/form-data" ACTION="?'.$urlenc.'&variation='.urlencode($variation).'&command=NEWPIC">'.$showerror;
								echo '<INPUT TYPE=HIDDEN NAME=csrf_token VALUE="' . $_SESSION['csrf_token'] . '">';
								echo '<BR><FONT class="owner-text">Brief Description: <INPUT TYPE=TEXT NAME=DESCRIP VALUE="" SIZE=80>';
								echo '<BR>Picture of this variation:<INPUT TYPE=FILE NAME="PHOTOPIC"></FONT>';
								echo '<INPUT TYPE=SUBMIT>';
								echo '</FORM>'."\n";
							}
							else
							{
								echo $showerror.'<TABLE BORDER=0 WIDTH=100%><TR><TD ALIGN=RIGHT><a href="?'.$urlenc.'&variation='.urlencode($variation).'&command=ADDPIC"><FONT class="owner-text">+pic</FONT></a></TD></TR></TABLE>'."\n";
							}
						}
					}
					if (($command == 'NEWSERIAL')
					&& ($variation == $curvariation)
					&& ($curname != '')
					&& (isset($_POST["NEWSERIAL"])))
					{
						$command = 'ADDSERIAL';
						$newser = trim($_POST["NEWSERIAL"]);
						if (strlen($newser) < 3)
							$showerror = '<FONT class="error-message">BAD Serial Number</FONT>';
						$filesz = $_FILES['SERIALPIC']['size'];
						if((!isset($filesz)) || ($filesz < 1))
							$showerror = '<FONT class="error-message">Pic of serial number required.</FONT>';
						if(isset($filesz) && ($filesz > 1024*1024))
							$showerror = '<FONT class="error-message">Too Large! Less than 1megabyte please.</FONT>';
						if(isset($_FILES["SERIALPIC"]["error"]) && ($_FILES["SERIALPIC"]["error"] != 0))
							$showerror = '<FONT class="error-message">Upload error: '.$_FILES["SERIALPIC"]["error"].'</FONT>';
						if (mtyp($_FILES['SERIALPIC']['name']) == '')
							$showerror = '<FONT class="error-message">Bad Type.  Try jpg, gif, etc</FONT>';
						if ($showerror == '')
						{
							$tmpname = $_FILES['SERIALPIC']['tmp_name'];
							$name = $_FILES['SERIALPIC']['name'];
							$content = file_get_contents($tmpname);
							$istmt = $pdo->prepare("UPDATE OWNERS SET SERIAL=:sr, SERIALPHOTONAME=:pn, SERIALPHOTO=:ph WHERE MODEL=:md AND VARI=:va AND NAME=:nm");
							$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation, ':nm' => $curname, ':sr' => $_POST["NEWSERIAL"], ':pn' => $name, ':ph' => $content));
							$command = '';
							doLog('Added own missing serial to variation '.addslashes($curvariation).' of model '.addslashes($curmodel));
						}
					}
					if (($command == 'NEWOWN')
					&& ($variation == $curvariation)
					&& ($curname != '')
					&& (isset($_POST["NEWSERIAL"])))
					{
						$command = 'ADDOWN';
						$newser = trim($_POST["NEWSERIAL"]);
						if (strlen($newser) < 3)
							$showerror = '<FONT class="error-message">BAD Serial Number</FONT>';
						$filesz = $_FILES['SERIALPIC']['size'];
						if((!isset($filesz)) || ($filesz < 1))
							$showerror = '<FONT class="error-message">Pic of serial number required.</FONT>';
						if(isset($filesz) && ($filesz > 1024*1024))
							$showerror = '<FONT class="error-message">Too Large! Less than 1megabyte please.</FONT>';
						if(isset($_FILES["SERIALPIC"]["error"]) && ($_FILES["SERIALPIC"]["error"] != 0))
							$showerror = '<FONT class="error-message">Upload error: '.$_FILES["SERIALPIC"]["error"].'</FONT>';
						if (mtyp($_FILES['SERIALPIC']['name']) == '')
							$showerror = '<FONT class="error-message">Bad Type.  Try jpg, gif, etc</FONT>';
						if ($showerror == '')
						{
							$tmpname = $_FILES['SERIALPIC']['tmp_name'];
							$name = $_FILES['SERIALPIC']['name'];
							$content = file_get_contents($tmpname);
							$istmt = $pdo->prepare("INSERT INTO OWNERS (NAME, MODEL, VARI, SERIAL, SERIALPHOTONAME, SERIALPHOTO, APPROVED) VALUES (:nm, :md, :va, :sr, :pn, :ph, 0)");
							$istmt->execute(array(':md' => $curmodel, ':va' => $curvariation, ':nm' => $curname, ':sr' => $_POST["NEWSERIAL"], ':pn' => $name, ':ph' => $content));
							$command = '';
							doLog('Added self as owner of variation '.addslashes($curvariation).' of model '.addslashes($curmodel));
						}
					}
					if ((($command == 'APPOWN')||($command == 'DELOWN'))
					&& ($privs > 1)
					&& ($variation == $curvariation)
					&& ($curname != '')
					&& (isset($_GET["owname"]))
					&& ($_GET["owname"] != ''))
					{
						if($command == 'APPOWN')
						{
							$jstmt = $pdo->prepare("UPDATE OWNERS SET APPROVED=1 WHERE NAME=:nm AND MODEL=:md AND VARI = :va");
							$jstmt->execute(array(':md' =>$curmodel, ':va' => $variation, ':nm' => $_GET["owname"]));
							doLog('Approved owner '.addslashes($_GET['owname']).' of variation '.addslashes($curvariation).' of model '.addslashes($curmodel));
							$jstmt = $pdo->prepare("UPDATE OWNERS SET APPROVED=1 WHERE NAME=:nm AND MODEL=:md AND VARI = :va");
							$jstmt->execute(array(':md' =>$curmodel, ':va' => $variation, ':nm' => $_GET["owname"]));
							$jstmt = $pdo->prepare("SELECT ACCESS FROM OWNER WHERE NAME=:nm");
							$jstmt->execute(array(':nm' => $_GET["owname"]));
							if($jrow = $jstmt->fetch())
							{
								$hisacc = $jrow["ACCESS"];
								if($hisacc < 1)
								{
									$jstmt = $pdo->prepare("UPDATE OWNER SET ACCESS=1 WHERE NAME=:nm");
									$jstmt->execute(array(':nm' => $_GET["owname"]));
								}
							}
						}
						else
						{
							$jstmt = $pdo->prepare("DELETE FROM OWNERS WHERE NAME=:nm AND MODEL=:md AND VARI = :va");
							$jstmt->execute(array(':md' =>$curmodel, ':va' => $variation, ':nm' => $_GET["owname"]));
							doLog('Deleted owner '.addslashes($_GET['owname']).' from variation '.addslashes($curvariation).' of model '.addslashes($curmodel));
						}
					}

					$sql = "SELECT OWNERS.NAME,SERIAL,PRIVATE,APPROVED FROM OWNERS INNER JOIN OWNER ON OWNER.NAME = OWNERS.NAME WHERE MODEL=:md AND VARI=:va";
					$istmt = $pdo->prepare($sql);
					$istmt->execute(array(':md' =>$curmodel, ':va' => $variation));
					$isowner = 0;
					$hasowner = 0;
					$hasgoodowner = 0;
					while($irow = $istmt->fetch()) 
					{
						if (($curname != '') && ($irow["NAME"] == $curname))
							$isowner = 1;
						$hasowner = 1;
						if(trim($irow["SERIAL"]) != '')
							$hasgoodowner = 1;
						if ($irow["APPROVED"] == 0)
						{
							if($isowner == 1)
								echo "<BR><FONT class=\"success-message\">Ownership registered and awaiting approval.</FONT>";
							if($privs < 2)
								continue;
						}
						if (($irow["PRIVATE"] == 0)||($privs > 1))
						{
							echo '<BR><FONT class="owner-text">Owner: '.$irow["NAME"];
							if(($irow["SERIAL"] != null) && ($irow["SERIAL"] != ''))
							{
								if($irow["PRIVATE"] == 0)
									echo ', serial#'.$irow["SERIAL"];
								else
									echo ', <FONT class="edit-link">serial#'.$irow["SERIAL"].'</FONT>';

								if (($irow["APPROVED"] == 0) && ($privs > 1)) 
								{
									$jstmt = $pdo->prepare("SELECT SERIALPHOTONAME, SERIALPHOTO FROM OWNERS WHERE MODEL=:md AND VARI=:va AND NAME=:nm");
									$jstmt->execute(array(':md' =>$curmodel, ':va' => $variation, ':nm' => $irow["NAME"]));
									if($jrow = $jstmt->fetch()) 
									{
										$mimetype = mtyp($jrow["SERIALPHOTONAME"]);
										echo '<BR><img width=400 src="data:'.$mimetype.';base64,'.base64_encode( $jrow['SERIALPHOTO'] ).'"/>';
									}
									echo '<a href="?'.$urlenc.'&variation='.$variation.'&owname='.urlencode($irow["NAME"]).'&command=APPOWN"><FONT class="success-message">(approve)</FONT></a>';
									echo '<a href="?'.$urlenc.'&variation='.$variation.'&owname='.urlencode($irow["NAME"]).'&command=DELOWN"><FONT class="error-message">(delete)</FONT></a>';
								}
							}
							else
							if (($curname != '') && ($irow["NAME"] == $curname))
							{
								if (($command == 'ADDSERIAL') && ($variation == $curvariation))
								{
									echo '<FORM NAME=SERRAL METHOD=POST ENCTYPE="multipart/form-data" ACTION="?'.$urlenc.'&variation='.urlencode($variation).'&command=NEWSERIAL">'.$showerror;
									echo '<INPUT TYPE=HIDDEN NAME=csrf_token VALUE="' . $_SESSION['csrf_token'] . '">';
									echo '<FONT class="owner-text">&nbsp;Serial:<INPUT TYPE=TEXT NAME="NEWSERIAL" SIZE=10>';
									echo ',&nbsp;&nbsp;&nbsp;&nbsp;Picture of Serial:<INPUT TYPE=FILE NAME="SERIALPIC">';
									echo '<INPUT TYPE=SUBMIT>';
									echo '</FORM>';
								}
								else
									echo $showerror.'<a href="?'.$urlenc.'&variation='.urlencode($variation).'&command=ADDSERIAL"><FONT class="owner-text">&nbsp;(add serial)</FONT></a>';
							}
							echo "</FONT>\n";
							if ($editflag == 1)
							{
								echo '<a href="?editthis=own&owname='.urldecode($irow["NAME"]).'&'.$urlenc.'"><FONT class="edit-link">(e)</FONT></a>';
								echo '&nbsp;&nbsp;&nbsp;&nbsp;<a href="?'.$urlenc.'&variation='.$variation.'&command=DELOWN&owname='.urldecode($irow["NAME"]).'"><FONT class="error-message">(x)</FONT></a>';
							}
						}
					}
					if($isowner == 0)
					{
						if (($curname != '') && ($command == 'ADDOWN') && ($variation == $curvariation))
						{
							echo '<FORM NAME=OWNBALL METHOD=POST ENCTYPE="multipart/form-data" ACTION="?'.$urlenc.'&variation='.urlencode($variation).'&command=NEWOWN">'.$showerror;
							echo '<INPUT TYPE=HIDDEN NAME=csrf_token VALUE="' . $_SESSION['csrf_token'] . '">';
							echo '<FONT class="owner-text"><a name="addown">&nbsp;</a>Serial:<INPUT TYPE=TEXT NAME="NEWSERIAL" SIZE=10>';
							echo ',&nbsp;&nbsp;&nbsp;&nbsp;Picture of Serial:<INPUT TYPE=FILE NAME="SERIALPIC">';
							echo '<INPUT TYPE=SUBMIT>';
							echo '</FORM>';
						}
						else
						if($aliasOf == NULL)
						{
							$isownerurl = '';
							if ($curname != '')
								$isownerurl = '?'.$urlenc.'&variation='.$variation.'&command=ADDOWN';
							else
								$isownerurl = '#login';
							echo '<TABLE BORDER=0 WIDTH=100%><TR><TD ALIGN=RIGHT><a href="'.$isownerurl.'"><FONT class="owner-text">I own one of these!</FONT></a></TD></TR></TABLE>'."\n";
						}
					}
					if($verified==0)
					{
						if($hasowner==0)
						{
							if ($curprod == 1)
								echo '<FONT class="unverified-text">The above is strongly believed to have been produced and sold, but has not been spotted in the wild yet.</FONT>';
							else
								echo '<FONT class="unverified-text">The above is rumored to have existed in some state, but has not been spotted in the wild.</FONT>';
							if(($privs > 1) && ($editflag==1))
								echo '<a href="?'.$urlenc.'&variation='.$variation.'&command=VTOGGLE"><FONT class="edit-link">(Mark as spotted)</FONT></a>';
							echo '<BR>';
						}
						else
						if($hasgoodowner != 0)
						{
							$fstmt = $pdo->prepare("UPDATE ENTRY SET VERIFIED=1 WHERE PRODUCED=:pd AND MODEL=:md AND CATNAME=:ca AND VARI=:va");
							$fstmt->execute(array(':pd' => $curprod, ':md' => $curmodel, ':ca' => $curcat, ':va' => $variation));
							$verified = 1;
						}
					}
					else
					if(($hasowner==0) && ($privs > 1) && ($editflag==1))
					{
						echo '<a href="?'.$urlenc.'&variation='.$variation.'&command=VTOGGLE"><FONT class="edit-link">(Mark as un-verified/un-spotted)</FONT></a><BR>';
					}
					echo '</TD></TR>';
				}
				if($editflag == 1)
				{
					echo "<TR><TD COLSPAN=2>";
					if($command == 'ADDVARIATION')
					{
						echo '<BR><BR><BR><BR><BR><BR>';
						echo '<FORM NAME=ADDVATTER METHOD=POST ENCTYPE="multipart/form-data" ACTION="?'.$urlenc.'&command=NEWVARIATION">';
						echo '<INPUT TYPE=HIDDEN NAME=csrf_token VALUE="' . $_SESSION['csrf_token'] . '">';
						echo '<TABLE BORDER=0 CELLSPACING=0 CELLPADDING=0>';
						echo '<TR><TD COLSPAN=2><FONT SIZE=3 class="model-link">Add New Variation: </FONT>'.$showerror.'</TD></TR>';
						echo '<TD><FONT SIZE=3 class="model-link">Desc: </FONT></TD>';
						echo '<TD><INPUT TYPE=TEXT NAME=DESCRIP VALUE="Description" SIZE=60>';
						echo '<INPUT TYPE=HIDDEN NAME=NEWCAT VALUE="'.htmlentities($curcat).'">';
						echo '</TD></TR><TR><TD>';
						echo '<INPUT TYPE=SUBMIT>';
						echo '</TD><TD ALIGN=LEFT><INPUT TYPE=HIDDEN NAME=DELALL VALUE=""></TD></TR></TABLE>';
						echo '</FORM>';
					}
					else
						echo '<BR><A HREF="?'.$urlenc.'&command=ADDVARIATION"><FONT class="edit-link">Add new variation</FONT></A>';
					echo '</TD></TR>'."\n";
				}
			}
			else
			{
				$stmt = $pdo->prepare("SELECT MODEL,VARI,SHORTBLURB FROM (SELECT MODEL,COUNT(VARI) AS VARI, SHORTBLURB FROM ENTRY WHERE CATNAME=:nm AND PRODUCED=:pd GROUP BY MODEL) AS T ORDER BY MODEL,VARI ");
				$stmt->execute(array(':nm' =>$curcat, ':pd' => $curprod));
				while ($row = $stmt->fetch())
				{
					echo "<TR><TD WIDTH=20%>";
					echo '<A HREF="?'.$urlenc.'&UPTOP&model='.urlencode($row["MODEL"]).'">';
					echo '<FONT class="model-link">'.htmlentities($row["MODEL"]).'</FONT></a>';
					if ($row["VARI"] > 1)
								echo '&nbsp;&nbsp;<FONT class="variations-count">('.htmlentities($row["VARI"]).')</FONT>';
					echo "</TD>";
					echo "<TD><FONT class=\"model-section\">".htmlentities($row["SHORTBLURB"])."</FONT></TD>";
					echo "</TR>\n";
				}
				if($editflag == 1)
				{
					echo "<TR><TD COLSPAN=2>";
					if($command == 'ADDMODEL')
					{
						echo '<BR><BR><BR><BR><BR><BR>';
						echo '<FORM NAME=ADDVATTER METHOD=POST ENCTYPE="multipart/form-data" ACTION="?'.$urlenc.'&command=NEWMODEL">';
						echo '<INPUT TYPE=HIDDEN NAME=csrf_token VALUE="' . $_SESSION['csrf_token'] . '">';
						echo '<TABLE BORDER=0 CELLSPACING=0 CELLPADDING=0>';
						echo '<TR><TD COLSPAN=2><FONT SIZE=3 class="model-link">Add New Model: </FONT>'.$showerror.'</TD></TR>';
						echo '<TR><TD><FONT SIZE=3 class="model-link">Model: </FONT></TD>';
						echo '<TD><INPUT TYPE=TEXT NAME=MODELNAME VALUE="New Model Name" SIZE=13>';
						echo '</TD></TR><TR>';
						echo '<TD><FONT SIZE=3 class="model-link">Desc: </FONT></TD>';
						echo '<TD><INPUT TYPE=TEXT NAME=DESCRIP VALUE="Description" SIZE=60>';
						echo '<INPUT TYPE=HIDDEN NAME=NEWCAT VALUE="'.htmlentities($curcat).'">';
						echo '</TD></TR><TR><TD>';
						echo '<INPUT TYPE=SUBMIT>';
						echo '</TD><TD ALIGN=LEFT><INPUT TYPE=HIDDEN NAME=DELALL VALUE=""></TD></TR></TABLE>';
						echo '</FORM>';
					}
					else
						echo '<BR><A HREF="?'.$urlenc.'&command=ADDMODEL"><FONT class="edit-link">Add new model</FONT></A>';
					echo "</TD></TR>\n";
				}
			}
			echo "</TABLE>\n";
		}
		else
		{
			$stmt = $pdo->query("select NAME,APPROVED,MODEL,VARI,PHOTONAME,PHOTOURL,DESCRIP from PHOTOS WHERE RAND() < (select 10 / COUNT(*) from PHOTOS);");
			$left = true;
			echo '<TABLE WIDTH=100% BORDER=0>';
			$ct = 0;
			while(($row = $stmt->fetch())&&($ct<5)) 
			{
				$ct = $ct + 1;
				$pname = $row["NAME"];
				if(($row["APPROVED"] == 0) 
				&&($privs<2)
				&&(($pname == '')||($curname != $pname)))
					continue;
				if($left)
					echo '<TR><TD WIDTH=50% ALIGN=CENTER>';
				else
					echo '<TR><TD WIDTH=50%>&nbsp;</TD><TD WIDTH=50% ALIGN=CENTER>';
				if(($row["PHOTOURL"] != null) && ($row["PHOTOURL"] != ''))
				{
					echo '<BR><img src="'.$row["PHOTOURL"].'" WIDTH=400>';
					if(($row["DESCRIP"] != null) && ($row["DESCRIP"] != ''))
						echo '<FONT class="photo-description">'.htmlentities($row["DESCRIP"]).'</FONT>';
					echo "<P>\n";
				}
				if(($row["PHOTONAME"] != null) && ($row["PHOTONAME"] != ''))
				{
					$mimetype = mtyp($row["PHOTONAME"]);
					$istmt = $pdo->query("select CATNAME,PRODUCED FROM ENTRY WHERE MODEL='".addslashes($row["MODEL"])."' AND VARI=".$row["VARI"].";");
					if($irow = $istmt->fetch())
						echo '<a href="?UPTOP&cat='.urlencode($irow["CATNAME"]).'&model='.urlencode($row["MODEL"]).'&variation='.$row["VARI"].'&prod='.$irow["PRODUCED"].$urladd.'">';
					echo '<img align=left src="?'.$urlenc.'&model='.urlencode($row["MODEL"]).'&command=IMG&variation='.$row["VARI"].'&iname='.urlencode($row["PHOTONAME"]).'&width=small"/><BR>';
					echo '<FONT class="model-link">'.htmlentities($row["MODEL"]).'</FONT></a>';
				}
				if($left)
					echo '</TD><TD WIDTH=50%>&nbsp;</TD></TR>';
				else
					echo '</TD></TR>';
				$left = !$left;
			}
			
		}
	?>
	</TD>
</TR></TABLE>
<SCRIPT LANGUAGE=JavaScript>
<!--
var top1 = localStorage.getItem("sidebar-scroll");
if (top1 !== null) {
  window.scrollTo(0,top1);
}

window.addEventListener("beforeunload", function(){
  if(document.activeElement.href.indexOf('UPTOP')>0)
	localStorage.setItem("sidebar-scroll", null);
  else
	localStorage.setItem("sidebar-scroll", window.pageYOffset);
});
//-->
</SCRIPT>
</BODY>
</HTML>