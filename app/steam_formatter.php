<?php
declare(strict_types=1);
function steam_bbcode_to_html(string $source): string {
    $source=html_entity_decode($source,ENT_QUOTES|ENT_HTML5,'UTF-8');$code=[];
    $source=preg_replace_callback('/\[code(?:=[^\]]+)?\](.*?)\[\/code\]/is',function(array $m)use(&$code):string{$key='___STEAM_CODE_'.count($code).'___';$code[$key]='<pre class="steam-code"><code>'.e($m[1]).'</code></pre>';return $key;},$source)??$source;
    $source=preg_replace('/\[h([1-6])\](.*?)\[\/h\1\]/is','<h$1>$2</h$1>',$source)??$source;
    $source=preg_replace('/\[b\](.*?)\[\/b\]/is','<strong>$1</strong>',$source)??$source;$source=preg_replace('/\[i\](.*?)\[\/i\]/is','<em>$1</em>',$source)??$source;$source=preg_replace('/\[u\](.*?)\[\/u\]/is','<u>$1</u>',$source)??$source;$source=preg_replace('/\[(?:strike|s)\](.*?)\[\/(?:strike|s)\]/is','<s>$1</s>',$source)??$source;$source=preg_replace('/\[quote\](.*?)\[\/quote\]/is','<blockquote>$1</blockquote>',$source)??$source;$source=preg_replace('/\[p\](.*?)\[\/p\]/is','<p>$1</p>',$source)??$source;$source=preg_replace('/\[hr\]/i','<hr>',$source)??$source;
    $source=preg_replace_callback('/\[url(?:=([^\]]+))?\](.*?)\[\/url\]/is',function(array $m):string{$href=$m[1]!==''?$m[1]:trim(strip_tags($m[2]));return '<a href="'.e($href).'">'.$m[2].'</a>';},$source)??$source;
    $source=preg_replace_callback('/\[img(?:=([^\]]+))?\](.*?)\[\/img\]/is',function(array $m):string{return '<img src="'.e(trim($m[2])).'" alt="" loading="lazy">';},$source)??$source;
    $source=str_ireplace(['[list]','[/list]','[olist]','[/olist]'],['<ul>','</ul>','<ol>','</ol>'],$source);$source=preg_replace('/\[\*\]\s*/i','<li>',$source)??$source;$source=preg_replace('/\[\/\*\]/i','</li>',$source)??$source;
    $source=preg_replace('/\[table\]/i','<div class="steam-table-wrap"><table>',$source)??$source;$source=preg_replace('/\[\/table\]/i','</table></div>',$source)??$source;$source=preg_replace('/\[tr\](.*?)\[\/tr\]/is','<tr>$1</tr>',$source)??$source;$source=preg_replace('/\[th\](.*?)\[\/th\]/is','<th>$1</th>',$source)??$source;$source=preg_replace('/\[td\](.*?)\[\/td\]/is','<td>$1</td>',$source)??$source;
    $source=steam_sanitize_html($source);$source=str_replace(["\r\n","\r"],"\n",$source);$source=preg_replace("/\n{3,}/","\n\n",$source)??$source;foreach($code as $key=>$html)$source=str_replace($key,$html,$source);return trim($source);
}
function steam_sanitize_html(string $html): string {
    if($html==='')return '';
    if(!class_exists('DOMDocument'))return nl2br(e(strip_tags($html)));
    $allowed=['p','br','strong','b','em','i','u','s','h1','h2','h3','h4','h5','h6','ul','ol','li','blockquote','pre','code','a','img','table','thead','tbody','tr','th','td','hr','div','span'];
    $dom=new DOMDocument('1.0','UTF-8');libxml_use_internal_errors(true);$wrapped='<!doctype html><html><body><div id="steam-root">'.$html.'</div></body></html>';
    if(!$dom->loadHTML('<?xml encoding="UTF-8">'.$wrapped,LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD)){libxml_clear_errors();return nl2br(e(strip_tags($html)));}
    $roots=$dom->getElementsByTagName('div');$root=null;foreach($roots as $candidate){if($candidate->getAttribute('id')==='steam-root'){$root=$candidate;break;}}if(!$root){libxml_clear_errors();return nl2br(e(strip_tags($html)));}
    $walk=function(DOMNode $node)use(&$walk,$allowed):void{
        if($node->nodeType===XML_ELEMENT_NODE){$tag=strtolower($node->nodeName);if(!in_array($tag,$allowed,true)){ $text=$node->ownerDocument->createTextNode($node->textContent??'');$node->parentNode?->replaceChild($text,$node);return; }
            if($node instanceof DOMElement){$attrs=[];foreach($node->attributes as $attr)$attrs[]=$attr->name;foreach($attrs as $attr)if(!in_array(strtolower($attr),['href','src','alt','title','target','rel','loading'],true))$node->removeAttribute($attr);
                if($tag==='a'){$href=trim($node->getAttribute('href'));if(!preg_match('#^https?://#i',$href))$node->removeAttribute('href');else{$node->setAttribute('target','_blank');$node->setAttribute('rel','noopener noreferrer');}}
                if($tag==='img'){$src=trim($node->getAttribute('src'));if(!preg_match('#^https?://#i',$src)){$node->parentNode?->removeChild($node);return;}else$node->setAttribute('loading','lazy');}
            }
        }
        foreach(iterator_to_array($node->childNodes) as $child)$walk($child);
    };
    foreach(iterator_to_array($root->childNodes) as $child)$walk($child);$out='';foreach($root->childNodes as $child)$out.=$dom->saveHTML($child);libxml_clear_errors();return $out;
}
function format_update_body(string $body,string $format='text'):string{return in_array($format,['steam_bbcode','steam_html'],true)?steam_bbcode_to_html($body):nl2br(e($body));}
