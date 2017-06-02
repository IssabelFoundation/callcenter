$(document).ready(function() {
	$('.reparar_file').click(function() {
		var id_agente = $(this).parents("tr").first().find("input:radio").val();
		confirmar_reparacion(id_agente, 'reparar_file', pregunta_borrar_agente_conf);
	});
	$('.reparar_db').click(function() {
		var id_agente = $(this).parents("tr").first().find("input:radio").val();
		confirmar_reparacion(id_agente, 'reparar_db', pregunta_agregar_agente_conf);
	});
});

function confirmar_reparacion(id_agente, action, pregunta)
{
    if (!confirm(pregunta)) return;
    $.post('index.php', {
    	menu:	'agents',
    	rawmode:	'yes',
		action:		action,
		id_agent:	id_agente
    }, function (respuesta) {
    	if (respuesta.status == 'error') {
    		alert(respuesta.message);
    	} else {
    		location.reload();
    	}
    });
}