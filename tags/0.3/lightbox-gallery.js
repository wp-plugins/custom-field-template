// You do not need to change the following path if you installed WordPress into the root directory.
var lightbox_path = 'http://'+location.hostname+'/wp-content/plugins/lightbox-gallery/';

jQuery(document).ready(function () {

// If you make images display slowly, use following two lines;
//	var i = 0;
//	showImg(i);

	jQuery('a[@rel*=lightbox]').lightBox();
	jQuery('.gallery1 a').lightBox({captionPosition:'gallery'});
	jQuery('.gallery1 a').Tooltip({track:true, delay:0, showURL: false});
  
// Add these lines if you want to handle multiple galleries in one page.
// You need to add into a [gallery] shorttag. ex) [gallery class="gallery2"] 
//  jQuery('.gallery2 a').lightBox({captionPosition:'gallery'});
//  jQuery('.gallery2 a').Tooltip({track:true, delay:0, showURL: false});
//  jQuery('.gallery3 a').lightBox({captionPosition:'gallery'});
//  jQuery('.gallery3 a').Tooltip({track:true, delay:0, showURL: false});
});

function showImg(i){
	if(i == jQuery('img').length){
		return;
	}else{
		jQuery(jQuery('img')[i]).animate({opacity:'show'},"normal",function(){i++;showImg(i)});
	}
}