<?php
	require 'LIB_http.php';
	require 'LIB_parse.php';
	require 'db.connection.php';
	
	//if today is weekend,exit.
	$date = date("Y-m-d");
	$weekDay = date('w', strtotime($date));
	if($weekDay == 0 || $weekDay == 6)
		exit();
	
	function parse_html($contents)
	{
		$result = array();
		$web_page = trim($contents); //This is html contents.
		
		//checking contents are not '數據載入中.....'
		if(mb_stristr($web_page,'數據'))
		{
			return $result;
		}
		
		$web_page = return_between($web_page,'<div class="md_middle"><div class="mm_03"><div class="mm_02"><div class="mm_01">','</div></div></div></div>',EXCL);
		$web_page = return_between($web_page,'<table class="baseTB listTB list_TABLE hasBD hasTH" cellspacing="0" cellpadding="0" border="0" width="100%" summary="">','</table>',EXCL);
		//$get_thead = return_between($web_page,'<thead>','</thead>',EXCL);
		$get_tbody = return_between($web_page,'<tbody>','</tbody>',EXCL);
		$get_date = parse_array($get_tbody,'<td width="8%" nowrap="nowrap">','</td>'); //日期
		$get_title = parse_array($get_tbody,'<a ','</a>'); //標題
		
		
		$get_date_len = count($get_date);
		$get_title_len = count($get_title);
		
		$res_count = 0;
		for($get_date_count=0;$get_date_count<$get_date_len;$get_date_count++)
		{
			$result[$res_count]['date'] = trim(return_between($get_date[$get_date_count],'<td width="8%" nowrap="nowrap">','</td>',EXCL));
			$res_count++;
		}
		
		$res_count = 0;
		for($get_title_count=0;$get_title_count<$get_title_len;$get_title_count++)
		{
			$result[$res_count]['title'] = get_attribute($get_title[$get_title_count],$attribute = 'title');
			$result[$res_count]['link'] = get_attribute($get_title[$get_title_count],$attribute = 'href');
			$res_count++;
		}
		
		return $result;
	}
	
	function http_get_save($url)
	{
		foreach($url as $key =>$val)
		{
			$web_page = http_get($val,$ref='');
			file_put_contents($key.'.html',$web_page);
		}
	}
	
	function parse_enews_html($contents)
	{
		$result = array();
		$web_page =trim($contents);
		//check contents are  included 數據載入中
		if(mb_stristr($web_page,'數據'))
			return $result;
		
		$web_page = return_between($web_page,'<table summary="list" cellspacing="0" cellpadding="0" border="0" width="100%" class="baseTB listSD">','</table>',EXCL);
		$get_div = parse_array($web_page,'<div class="h5">','</div>');
		
		$res_count = 0;
		foreach($get_div as $val)
		{
			$val = return_between($val,'<div class="h5">','</div>',EXCL);
			$get_date = return_between($val,'<span class="date float-right">','</span>',EXCL); //日期
			$get_title = parse_array($val,'<a ','</a>'); //標題
			$get_title_len = count($get_title);
			
			$result[$res_count]['date'] = trim($get_date,'ght\" > []');
			
			for($get_title_count=0;$get_title_count<$get_title_len;$get_title_count++)
			{
				$result[$res_count]['title'] = get_attribute($get_title[$get_title_count],$attribute = 'title');
				$result[$res_count]['link'] = get_attribute($get_title[$get_title_count],$attribute = 'href');
			}
			
			$res_count++;
		}
		
		return $result;
	}
	
	/*
		top_news => '置頂公告'
		activity_news => '校園活動公告'
		admin_news => '行政公告'*
		academy_news => '學術公告'*
		hiring_news => '徵人啟事'
		released_news => '招生放榜'*
	*/
	
	$url = array(
		'top_news'=>'http://www.nttu.edu.tw/files/501-1000-1009-1.php',
		'activity_news'=>'http://www.nttu.edu.tw/files/501-1000-1021-1.php',
		'admin_news'=>'http://www.nttu.edu.tw/files/501-1000-1010-1.php',
		'academy_news'=>'http://www.nttu.edu.tw/files/501-1000-1012-1.php',
		'hiring_news'=>'http://www.nttu.edu.tw/files/501-1000-1011-1.php',
		'released_news'=>'http://www.nttu.edu.tw/files/501-1000-1013-1.php',
		'enews' => 'http://enews.nttu.edu.tw/files/40-1041-923-1.php' //special parse html file, referenced by parse_enews_html()
	);
	
	http_get_save($url);
	
	$link_db==null;
	$link_db = db_connection();
	if($link_db==null)
		echo "cannot link db.";
	else
	{
		foreach($url as $key=>$val)
		{
			if($key=="enews")
				break;
			$web_page = file_get_contents($key.".html");
			$result = parse_html($web_page);
			if(count($result)==0)
			{
				file_put_contents('error_log.txt','This error occured in '.date('Y-m-d').' and  This site aliased name is : '.$key.'\r\n',FILE_APPEND);
			}
			else
			{
				//After parsing htlml file successfully,start storing message in the MySQL database.
				//Of couse, this part except the enews' website.
				$result_len = count($result);
				for($res_count=0;$res_count<$result_len;$res_count++)
				{
					$check_inserted = "SELECT COUNT(*) AS res_count FROM ".$key." WHERE date = :date AND title = :title";
					$stmt = $link_db -> prepare($check_inserted);
					$stmt -> execute(array(":date"=>$result[$res_count]['date'],":title"=>$result[$res_count]['title']));
					if($stmt -> fetchColumn()!=0)
						continue;
					$sql = "INSERT INTO ".$key."(date,title,link) VALUES(:date,:title,:link)";
					$stmt = $link_db -> prepare($sql);
					$stmt -> execute(array(":date"=>$result[$res_count]['date'],":title"=>$result[$res_count]['title'],":link"=>$result[$res_count]['link']));
				}
			}
		}
		
		//The enews.html file is special so we have to invidually  call another parsing  function and store data in MySQL database.
		$web_page = file_get_contents("enews.html");
		$parse_res = parse_enews_html($web_page);
		$parse_res_len = count($parse_res);
		if(count($result)==0)
		{
			file_put_contents('error_log.txt','This error occured in '.date('Y-m-d').' and  This site aliased name is : enews.html\r\n',FILE_APPEND);
		}
		else
		{
			for($parse_res_count=0;$parse_res_count<$parse_res_len;$parse_res_count++)
			{
					$check_inserted = "SELECT COUNT(*) AS res_count FROM  enews  WHERE date = :date AND title = :title";
					$stmt = $link_db -> prepare($check_inserted);
					$stmt -> execute(array(":date"=>$parse_res[$parse_res_count]['date'],":title"=>$parse_res[$parse_res_count]['title']));
					if($stmt -> fetchColumn()!=0)
						continue;
				$sql = "INSERT INTO enews(date,title,link) VALUES(:date,:title,:link)";
				$stmt = $link_db -> prepare($sql);
				$stmt -> execute(array(":date"=>$parse_res[$parse_res_count]['date'],":title"=>$parse_res[$parse_res_count]['title'],":link"=>$parse_res[$parse_res_count]['link']));
			}
		}
		
		//刪除檔案,留下error_log.txt , 將今日日期離一個月的公告刪除
		foreach($url as $key=>$val)
		{
			$stmt = $link_db -> query("DELETE FROM ".$key." WHERE DATEDIFF(NOW(),date)=30");
			@unlink($key.'.html');
		}
		
		$link_db = null;
	}
?>
