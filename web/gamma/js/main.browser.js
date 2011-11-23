/********************************************

	MAIN.JS
	
	VERSION 0.1
	
	LOADS JS FILES


*********************************************/

var loadFiles = [
	'jquery',
	
	//css?
	//'text!../css/all.css', //not quite
	
	//config
	'order!zeega.config',
	
	//libraries
	'order!libraries/underscore',
	'order!libraries/backbone',
	'order!libraries/spin',
	'order!libraries/swfobject', // sfwobject should probably be somewhere else. helpers? plugins?
	'order!jquery/ui/js/jquery-ui.min',
	
	//custom

	'order!ux/zeega.ux.header',
	'order!ux/zeega.ux.browser',
	'order!helpers/zeega.extends',
	'order!helpers/zeega.helpers',

	//core
	'order!core/zeega',
	
	//plugins
	'order!jquery/plugins/spin',
	'order!jquery/plugins/jeditable.min',
	'order!jquery/plugins/jquery-cycle',
	'order!jquery/plugins/jquery.paging',
	'order!jquery/plugins/colorpicker/js/colorpicker',
	'order!jquery/plugins/jquerygetUrlParam',
	'order!jquery/plugins/jqueryjson',
	'order!jquery/plugins/jquerycookie',
	'order!jquery/plugins/jquerytmpl',
	'order!jquery/plugins/bootstrapmodal',
	
	
	
	//mvc
	'order!backbone/models/item',
	'order!backbone/models/browser.myCollections',
	'order!backbone/models/browser.search',
	
	

	'order!backbone/views/browser.my-collections-view',
	'order!backbone/views/browser.search-item-view',
	'order!backbone/views/browser.search-items-view',
	'order!backbone/views/browser.search-map-view',
	'order!backbone/views/browser.search-time-view',
	

	];

require(loadFiles, function($) {
    
	
	
	
	
	initUX();
	
});