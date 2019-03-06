$(document).ready(function() {
	var module_name = 'campaign_lists';

	var indexInterval = setInterval(function() {
		if($("#JColResizer0").length)
		{
			$.get('index.php', {
				menu: module_name,
				rawmode: 'yes',
				action: 'getLists'
			}, 'json').then(function(respuesta) {
				var list = null;
				for (_list in respuesta.lists) {
					list = respuesta.lists[_list];
					$("#total_calls_" + list.id).text(list.total_calls);
					$("#pending_calls_" + list.id).text(list.pending_calls);
				}
			});
		}
		else
		{
			clearInterval(indexInterval);
		}

	}, 60000);
});