<?php
/**
 * version 2018-11-05
 * FIXME - add to main app
 */

$title = '';

function getList($type, $lang, $mdlang, $withValues=false, $handler=""){
    if(!$handler) $handler="formats1";
    if(in_array($type, array('coordSys','format','limitationsAccess', 'accessCond', 'protocol', 'inspireKeywords', 'hlname', 'linkageName', 'serviceType'))){
        $xml = simplexml_load_file(APP_DIR . "/config/codelists.xml");
        $title = $xml->xpath("//$type/title[@lang='".$lang."']")[0];
        echo '<div class="panel-heading">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4>'.(string) $title .'</h4>
        </div><div class="modal-body">';
        $list = $xml->xpath("//$type/value");
        foreach ($list as $row){
            echo "<a href=\"javascript:$handler({uri:'".$row['uri']."', ";
            foreach($row as $k=>$v){
                if($k!='uri'){
                    echo "$k:'".$v."',";
                }
            }
            echo "xxx:'".(string) $row->$mdlang."'});\">".(string) $row->$lang."</a><br>";
        }
        echo "</div>";
        return;
    }
    else if(in_array($type, array('specifications'))){
        $xml = simplexml_load_file(APP_DIR . "/config/codelists.xml");
        $title = $xml->xpath("//$type/title[@lang='".$lang."']")[0];
        echo '<div class="modal-headerx panel-heading">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4>'.(string) $title .'</h4>
        </div><div class="modal-body">';
        $list = $xml->xpath("//$type/value");
        foreach ($list as $row){
            echo "<a href=\"javascript:formats1({uri:'".$row['uri']."', ";
            foreach($row as $k=>$v){
                if($k!='uri'){
                    echo "$k:'".$v['name']."',";
                }
            }
            echo "xxx:'".(string) $row->$mdlang."'});\">".(string) $row->$lang."</a><br>";
        }
        echo "</div>";
        return;
    }

	@$xml = simplexml_load_file(APP_DIR . "/model/dict/$type.xml");
	if(!$xml) die("list <b>$type</b> does not exist");
	// test jazyka
	$langBranch = $xml->xpath("//translation[@lang='".$lang."']");
	if(isset($langBranch[0]) === FALSE) $lang='eng';
	$pageTitle = $xml->xpath("//translation[@lang='".$lang."']/title");
    echo '<div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4>'. $pageTitle[0].'</h4>
        </div><div class="modal-body">';
	foreach ($xml->xpath("//translation[@lang='".$lang."']/group") as $list) {
    	echo "<h3>".$list->title.'</h3>';
    	foreach ($list->entry as $entry){
    		// pouzije primarne label, kdyz neni, tak hodnotu
    		$value = $entry['label'];
    		if(!$value) $value = (string) $entry;
            $f = (isset($_REQUEST['handler']) && $_REQUEST['handler']) ? $_REQUEST['handler'] : 'false';
    		if($withValues) echo "<a href=\"javascript:micka.fillValues('".$type."','".$entry['id']."', ".$f.");\">".$value."</a><br>";
    		else echo "<a href=\"javascript:kw('".$entry['code']."');\">".(string) $entry."</a><br>";
    	}
	}
    echo "</div>";
}

function getCodeListValues($type, $lang, $filter=''){
    $xml = simplexml_load_file(APP_DIR . "/config/codelists.xml");
    $list = $xml->xpath("//$type/value");
    $result = array();
    foreach ($list as $row){
        if(!$filter || stripos($row->$lang, $filter)!==false || stripos($row['uri'], $filter)!==false){
            $result[] = array(
                "id"=> (string) $row['name'],
                "uri"=> (string) $row['uri'],
                "text"=> (string) $row->$lang
            );
        }
    }
    return array("results"=>$result);
}


if(isset($_REQUEST['request']) && $_REQUEST['request'] == 'getValues') {
    $type = htmlspecialchars($_REQUEST['type']);
    $code = htmlspecialchars($_REQUEST['id']);
    $lang = htmlspecialchars($_REQUEST['lang']);
    header("Content-type: application/json; charset=utf-8");
    echo json_encode(getCodeListValues($type, $lang, $_REQUEST['query']));
    exit;
}
?>
<script>
function kw(f){
<?php 
    if(isset($_REQUEST['handler']) && $_REQUEST['handler']) echo htmlspecialchars($_REQUEST['handler'])."(f);";
    else echo "formats1(f);";
?>
}
</script>

<?php
    $lang = htmlspecialchars($_REQUEST['lang']);
    $mdlang = htmlspecialchars($_REQUEST['mdlang']);
    $handler = htmlspecialchars($_REQUEST['handler']) ? htmlspecialchars($_REQUEST['handler']) : false;
    if(!$lang) $lang='eng';
    if(!$mdlang) $mdlang='eng';
    if(isset($_REQUEST['multi']) && $_REQUEST['multi']==1) {
        $multi = true;
    } else {
        $multi = false;
    }
    echo getList(htmlspecialchars($_REQUEST['type']), $lang, $mdlang, $multi, $handler);

