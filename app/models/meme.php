<?php
class Meme extends AppModel {

	var $name = 'Meme';
	var $hasMany = array(
		'MemeCaption' => array(
			'className' => 'MemeCaption',
			'foreignKey' => 'meme_id',
			'dependent' => false
		),
		'MemeTag'	=> array(
			'className'=> 'MemeTag',
			'foreignKey'=>'meme_id',
			'dependent'=>false
		)
	);

	var $paginate_limit = '30';
	var $paginate ="";
	var $sportsIndex = array(
		'NFL','NBA','NHL','Soccer','Hockey','Basketball',
		'NCAAB','Basketball','Football','College Football','NCAAF',
		'Golf'
	);
	function findDefaults(){
		return array();
	}
	
	function afterFind($results){
		for($i=0; $i<count($results); $i++ ) {
			if(isset($results[$i]['Meme']['title'])){
				$title=$results[$i]['Meme']['title'];
				$results[$i]['Meme']['url']=$this->getUrlPart($title.'-'.$results[$i]['Meme']['id']);
			} 
			if(isset($results[$i]['MemeTag'])){
				for($z=0;$z<count($results[$i]['MemeTag']);$z++){
					$results[$i]['MemeTag'][$z]['slug'] = $this->getUrlPart($results[$i]['MemeTag'][$z]['tag_name']);
				}
			}
		}
		return $results;
	}	
	
	function checkMemeOwner($meme_id,$user){ 
		if($user['User']['admin']==1){
			return true;
		}
		
		$meme = $this->find('first',array('fields'=>array('user_id'),'conditions'=>array('Meme.id'=>$meme_id)));
		if($meme['Meme']['user_id']!=$user['User']['id']){
			return false;
		}
		return true;

	}

	function deleteMeme($meme_id){
		$d=array('id'=>$meme_id,'deleted'=>1,'active'=>0);
		$this->set($d);
		$this->save();
		//$this->delete($meme_id);
		return true;

	}
	function getCaptionSizes(){
		$data[]= array('id'=>'auto','value'=>'Size: Auto');
		for($i=10;$i<=20;$i++){
			$data[]=array('id'=>$i,'value'=>'Size: '.$i.'pt');
		}
		$i=22;
		while($i<66){
			$data[]=array('id'=>$i,'value'=>'Size: '.$i.'pt');
			$i=$i+2;		
		}			
		return $data;
	}
	
	function getAlignMentOptions(){
	//	$arr= array('left top','left center','left bottom','right top','right center','right bottom','center top','center center','center bottom');
		$arr = array('center'=>'Center Align','left'=>'Left Align','right'=>'Right Align');
		return $arr;
	}
	
	function updateViewCount($meme_id,$current_count){
		$d['id']=$meme_id;
		$d['view_count']=$current_count+1;
		$this->set($d);
		$this->save();
	}

	function updateRating($meme_id,$new_rating_value){
		if($new_rating_value!==false && ($new_rating_value > 0 || $new_rating_value < 0)){//only update if we're changing the rating.
			$current = $this->find('first',array(
				'fields'=>array('Meme.id,Meme.rating'),
				'conditions'=>array('Meme.id'=>$meme_id)
			));
			if(!empty($current)){
				$updated_rating = $current['Meme']['rating']+$new_rating_value;//either +1 or -1.
				$update = array('id'=>$current['Meme']['id'],'rating'=>$updated_rating);
				$this->set($update);
				$this->save();
			}
		}
	}
	function getRandomMemes($limit,$page){
		$conditions[] = array('active'=>1);
		 
		//do a quick count on the memes table in order to calculate our coefficient
		$meme_count = $this->find('count',array('conditions'=>$conditions));
		
		$fragment = $limit/$meme_count; //this value should be a very small decimal.
		
		$conditions[] = array('RAND() <= '=>$fragment);
		
		$data = $this->find('all',
			array('fields'=>array('Meme.title,Meme.image_url,Meme.image_url_medium,Meme.id,Meme.rating,Meme.view_count,Meme.created'),
				'conditions'=>array($conditions),
				'recursive'=>-1,
				'limit'=>$limit));
		return $data;
	
	}
	function getMemesByPopularity($sort=null,$params=null){
		//date range passed through in format of days.  ie 2, 7, 30, etc.
		if(is_array($sort)){
			$sort = $sort['sort'];
		}
		
		$conditions[]=array('active'=>1);
		if(is_numeric($sort) && in_array($sort,array('2','7','30'))){
			$created = date('Y-m-d',strtotime("-".$sort." days"));
			$conditions[] = array("DATE_FORMAT(Meme.created, '%Y-%m-%d') >="=>$created);
		}
		if($sort=='new'){
			$order = 'Meme.id DESC';
		} else{
			$conditions[] = array("Meme.view_count >"=>1);
			$order = 'Meme.view_count DESC';
		}
		$page = 1;
		if(isset($params['form']['page'])){
			$page = $params['form']['page'];
		}
		$data = $this->find('all',array('conditions'=>$conditions,'order'=>$order,'limit'=>30,'page'=>$page));
		return $data;
	}
	function getMemes($category_id=null,$limit=null,$order=null,$page=null){
 		$conditions[] = array('active'=>1);
 		if($category_id != null){
 			$conditions[] = array('category_id'=>$category_id);
 		}
 		if($order != null){
	 		$order = $order;
 		}else{
 		 	$order = '';
 		}
		 		
  		$data = $this->find('all',
  			array('fields'=>array('Meme.title,Meme.image_url,Meme.image_url_medium,Meme.id,Meme.rating,Meme.view_count,Meme.created'),
  				'conditions'=>$conditions,
  				'recursive'=>-1,
  				'limit'=>($limit!=null)?$limit:10));
		return $data;
	}

	function getOriginals($limit){
		$order=$this->setOrder();
		$data=$this->find('all',array('conditions'=>array('is_original'=>1,'active'=>1),'order'=>$order,'limit'=>$limit));
		return $data;
	}
	function grabMemesByParent($parent_id,$params=null){
		//$cond[] = array('OR'=>array('parent_id'=>$parent_id,'Meme.id'=>$parent_id),'active'=>1);
		//print 'parent '.$parent_id;

		$cond[] = array('parent_id'=>$parent_id,'active'=>1);	
		$sort = (isset($params['sort']))?$params['sort']:'';
		$order = $this->setOrder($sort);
		$page = 1;
		if(isset($params['form']['page'])){
			$page = $params['form']['page'];
		}

		$data = $this->find('all',array(
			'conditions'=>$cond,
			 'order'=>$order,
			 'page'=>$page)
		);

		//print_r($data);
		return $data;
	
	}
	function setOrder($sort=null){
		$order = 'Meme.rating DESC'; 
		if($sort == 'viewcount'){
			$order = 'view_count DESC';
		}
		elseif($sort == 'newest' || $sort=='new'){
			$order = 'Meme.id DESC';
		}
		elseif($sort == 'oldest'){
			$order = 'Meme.id ASC';
		}
		elseif($sort == 'random'){
			$order  = 'RAND()';
		}
		return $order;
	}

	function grabMemesByLeague($league_id,$params=null){
		if(is_array($league_id)){
			$league_id = array_keys($league_id);
		}
		$sort = (isset($params['sort']))?$params['sort']:'';

		//league_id can be a single value OR an array.
		$cond[] = array('league_id'=>$league_id,'active'=>1,'image_url !='=>'');
		if(isset($params['team_id']) && $params['team_id']>0){
			$cond[] = array('team_id'=>$params['team_id']);
		}
		$order = $this->setOrder($sort);
		$data = $this->find('all',array(
			'conditions'=>$cond,
			'order'=>$order));
		return $data;
	}
	
	/*attempt to create an image containing the error message given.
    if this works, the image is sent to the browser. if not, an error
    is logged, and passed back to the browser as a 500 code instead.*/
	function fatal_error($message){
	    // send an image
	    if(function_exists('ImageCreate'))
	    {
	        $width = ImageFontWidth(5) * strlen($message) + 10 ;
	        $height = ImageFontHeight(5) + 10 ;
	        if($image = ImageCreate($width,$height))
	        {
	            $background = ImageColorAllocate($image,255,255,255) ;
	            $text_color = ImageColorAllocate($image,0,0,0) ;
	            ImageString($image,5,5,5,$message,$text_color) ;
	            header('Content-type: image/png') ;
	            ImagePNG($image) ;
	            ImageDestroy($image) ;
	            exit ;
	        }
	    }
	
	    // send 500 code
	    header("HTTP/1.0 500 Internal Server Error") ;
	    print($message) ;
	    exit ;
	}
	
	/*decode an HTML hex-code into an array of R,G, and B values.
    accepts these formats: (case insensitive) #ffffff, ffffff, #fff, fff*/
	function hex_to_rgb($hex) {
	    // remove '#'
	    if(substr($hex,0,1) == '#')
	        $hex = substr($hex,1) ;
	
	    // expand short form ('fff') color to long form ('ffffff')
    	if(strlen($hex) == 3) {
    	    $hex = substr($hex,0,1) . substr($hex,0,1) .
    	           substr($hex,1,1) . substr($hex,1,1) .
    	           substr($hex,2,1) . substr($hex,2,1) ;
    	}
	
	    if(strlen($hex) != 6)
	        fatal_error('Error: Invalid color "'.$hex.'"') ;

	    // convert from hexidecimal number systems
	    $rgb['red'] = hexdec(substr($hex,0,2)) ;
	    $rgb['green'] = hexdec(substr($hex,2,2)) ;
	    $rgb['blue'] = hexdec(substr($hex,4,2)) ;
	
	    return $rgb ;
	}

 
	function findPrevAndNext($meme_id){ 
  		$info['prev'] = $this->find('first',array('conditions'=>array('Meme.id <'=>$meme_id,'active'=>1,'deleted'=>0),'order'=>'Meme.id DESC','limit'=>1));
	 	$info['next'] = $this->find('first',array('conditions'=>array('Meme.id >'=>$meme_id,'active'=>1,'deleted'=>0),'order'=>'Meme.id ASC','limit'=>1));
	 	return $info;
  	}

  	function findSearchResults($term){
  		$rows = $this->find('all',array('conditions'=>array('Meme.title LIKE'=>'%'.$term.'%','active'=>1,'deleted'=>0),'order'=>'Meme.rating DESC','limit'=>20));
  		$return_array = array();
		foreach($rows as $row){
			$row_arr['label'] = $row['Meme']['title'];
			$row_arr['value'] = $row['Meme']['title'];//id
			$row_arr['url'] = $row['Meme']['url'];
			array_push($return_array,$row_arr);
			//$data['results'][] = array('id'=>$row['Meme']['id'],'label'=>$row['Meme']['title'],'value'=>$row['Meme']['title']);
		}


  		return $return_array;
  	}


  	function getSortParam($getParams,$postParams){
		$sort = '';
  		if(isset($getParams['s'])){
  			$sort = $getParams['s'];
  		} elseif(isset($getParams['sort'])){
  			$sort = $getParams['sort'];
  		} elseif(isset($postParams['form']['sort'])){
  			$sort = $postParams['form']['sort'];
  		}
  		return $sort;

  	}

  	function baseOnParent($parent_id){
  		$meme_img = $this->find('first',array("conditions"=>array("Meme.id"=>$parent_id)));
  		$new_meme = array(
  			'title'=>$meme_img['Meme']['title'],
  			'type_id'=>$meme_img['Meme']['type_id'],
  			'parent_id'=>$meme_img['Meme']['parent_id'],
  			'image_url_original'=>$meme_img['Meme']['image_url_original'],
  			'color'=>$meme_img['Meme']['color'],
  			'mime_type'=>$meme_img['Meme']['mime_type'],
  			'active'=>1,
  			'league_id'=>$meme_img['Meme']['league_id']
  		);
  		return $new_meme;
  	}

  	function getUserMemeCount($user_id,$ip_fallback=null){
  		return $this->find('count',array('conditions'=>array('user_id'=>$user_id,'deleted'=>0)));
  	}
  	/**
  	 * Writes the given text with a border into the image using TrueType fonts.
  	 * @author John Ciacia 
  	 * @param image An image resource
  	 * @param size The font size
  	 * @param angle The angle in degrees to rotate the text
  	 * @param x Upper left corner of the text
  	 * @param y Lower left corner of the text
  	 * @param textcolor This is the color of the main text
  	 * @param strokecolor This is the color of the text border
  	 * @param fontfile The path to the TrueType font you wish to use
  	 * @param text The text string in UTF-8 encoding
  	 * @param px Number of pixels the text border will be
  	 * @see http://us.php.net/manual/en/function.imagettftext.php
  	 */
  	function imagettfstroketext(&$image, $size, $angle, $x, $y, &$textcolor, &$strokecolor, $fontfile, $text, $px) {
  	 	//print $size;exit;
  	    for($c1 = ($x-abs($px)); $c1 <= ($x+abs($px)); $c1++)
  	        for($c2 = ($y-abs($px)); $c2 <= ($y+abs($px)); $c2++)
  	            $bg = imagettftext($image, $size, $angle, $c1, $c2, $strokecolor, $fontfile, $text);
  	 
  	   return imagettftext($image, $size, $angle, $x, $y, $textcolor, $fontfile, $text);
  	}
}
?>
