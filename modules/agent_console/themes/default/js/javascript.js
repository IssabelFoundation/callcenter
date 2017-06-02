var module_name = 'agent_console';

// Variables de mensajes internacionalizados
var schedule_call_error_msg_missing_date = '';

/* El siguiente objeto es el estado de la interfaz del CallCenter. Al comparar
 * este objeto con los cambios de estado producto de eventos del ECCP, se
 * consigue detectar los cambios requeridos a la interfaz sin tener que recurrir
 * a llamadas repetidas al servidor.
 * Este objeto se inicializa en initialize_client_state() */
var estadoCliente =
{
	onhold:		false,	// VERDADERO si el sistema está en hold
	break_id:	null,	// Si != null, el ID del break en que está el agente
	calltype:	null,	// Si != null, tipo de llamada incoming/outgoing
	campaign_id:null,	// ID de la campaña a que pertenece la llamada atendida
	callid:		null,	// Si != null, ID de llamada que se está atendiendo

	// Por ahora sólo se modela si se espera o no una llamada manual
	waitingcall: false
};

/* Al cargar la página, o al recibir un evento AJAX, si timer_seconds tiene un
 * valor distinto de null, se inicia fechaInicio a la fecha y hora actual menos
 * el valor en segundos indicado por timer_seconds. En cualquier momento futuro,
 * el valor correcto del contador es la fecha actual menos la almacenada en
 * fechaInicio. */
var fechaInicio = null;
var timer = null;

// Objeto EventSource, si está soportado por el navegador
var evtSource = null;

// Copia del URL a cargar al agregar la nueva cejilla
var jqueryui_tabs_use_refresh = true;
var externalurl = null;
var externalurl_title = null;

$(document).ready(function() {
    $('#elastix-callcenter-error-message').hide();
    $('#elastix-callcenter-info-message').hide();
    $('#elastix-callcenter-agendar-llamada-error-message').hide();

    $('#label_extension_callback').hide();
    $('#input_extension_callback').hide();
    $('#label_password_callback').hide();
    $('#input_password_callback').hide();

    $('#btn_hangup').button();
    $('#btn_togglebreak').button();
    $('#btn_transfer').button();
    $('#btn_vtigercrm').button();
    $('#btn_logout').button();
    $('#btn_confirmar_contacto').button();
    $('#btn_confirmar_contacto').click(do_confirm_contact);
    $('#btn_agendar_llamada').button();
    $('#schedule_same_agent').button();
    $('#schedule_radio').buttonset();
    $('#transfer_type_radio').buttonset();
    $('#btn_guardar_formularios').button();
    $('#schedule_date').hide();
    $('#elastix-callcenter-cejillas-contenido').tabs({
        // Este evento sólo se dispara para jQueryUI < 1.9.0
        add:    function (event, ui) {
            if (externalurl != null)
                $(ui.panel).append("<iframe scrolling=\"auto\" height=\"450\" frameborder=\"0\" width=\"100%\" src=\"" + externalurl + "\" />");
            externalurl = null;
        }
    });

    // Verificar versión de jQueryUI para manejo de tabs
    // map() sobre Array no existe en IE8
    //var curr_uiversion = t_curr_uiversion.map(function(x) { return parseInt(x); });
    var curr_uiversion = [];
    var t_curr_uiversion = $.ui.version.split('.');
    for (var i = 0; i < t_curr_uiversion.length; i++) curr_uiversion[i] = parseInt(t_curr_uiversion[i]);
    var min_uiversion = [1,9,0];
    while (curr_uiversion.length > min_uiversion.length) min_uiversion.push(0);
    while (curr_uiversion.length < min_uiversion.length) curr_uiversion.push(0);
    while (curr_uiversion.length > 0) {
        var a = curr_uiversion.shift();
        var b = min_uiversion.shift();
        if (a > b) {
            jqueryui_tabs_use_refresh = true;
            break;
        }
        if (a < b) {
            jqueryui_tabs_use_refresh = false;
            break;
        }
    }

    if ($('#elastix-callcenter-llamada-paneles').length > 0) {
        $('#elastix-callcenter-llamada-paneles').layout({fxName: 'none', west: { size: 300 }});
        $('#elastix-callcenter-llamada-paneles-izq').layout({fxName: 'none', south: { size: 250 }});
    }

    // Operaciones que deben de repetirse al obtener formulario vía AJAX
    apply_form_styles();

    $('#submit_agent_login').click(do_login);
    $('#btn_logout').click(do_logout);
    $('#btn_hangup').click(do_hangup);

    // El siguiente código se ejecuta al hacer click en el botón de break
    $('#btn_togglebreak').click(function() {
    	if ($('#btn_togglebreak').hasClass('elastix-callcenter-boton-unbreak')) {
    		do_unbreak();
    	} else {
    		// Botón está en estado de elegir break
    		$('#elastix-callcenter-seleccion-break').dialog('open');
    	}
    });

    // Botón para guardar formularios
    $('#btn_guardar_formularios').click(do_save_forms);

    $('#btn_transfer').click(function() {
		$('#elastix-callcenter-seleccion-transfer').dialog('open');
    });
    $('#btn_agendar_llamada').click(function() {
		$('#elastix-callcenter-agendar-llamada').dialog('open');
    });

    // El siguiente código se ejecuta al presionar el botón de VTiger
    $('#btn_vtigercrm').click(function() {
    	window.open("/vtigercrm/","vtigercrm");
    });

    var fechasAgenda = $('#schedule_date_start, #schedule_date_end').datepicker({
    	minDate:		0,
    	showOn:			'both',
    	buttonImage:	'images/calendar.gif',
    	buttonImageOnly: true,
    	showButtonPanel: true,
    	dateFormat:		'yy-mm-dd',
    	onSelect:		function (selectedDate) {
    		// Al seleccionar la fecha en un calendario, el otro se restringe
    		var option = (this.id == "schedule_date_start") ? "minDate" : "maxDate",
				instance = $( this ).data( "datepicker" ),
				date = $.datepicker.parseDate(
						instance.settings.dateFormat ||
						$.datepicker._defaults.dateFormat,
						selectedDate, instance.settings );
    		fechasAgenda.not( this ).datepicker( "option", option, date );
    	}
    });
    $('#schedule_type_campaign_end').change(function() {
    	$('#schedule_date').hide();
    });
    $('#schedule_type_bydate').change(function() {
    	$('#schedule_date').show();
    });

    $('#input_callback').click(function() {
		var $this = $(this);
		// $this will contain a reference to the checkbox
		if ($this.is(':checked')) {
		    $('#input_extension').hide();
		    $('#input_agent_user').hide();
		    $('#label_extension').hide();
		    $('#label_agent_user').hide();

		    $('#label_extension_callback').show();
		    $('#input_extension_callback').show();
		    $('#label_password_callback').show();
		    $('#input_password_callback').show();

		} else {
		    $('#input_extension').show();
		    $('#input_agent_user').show();
		    $('#label_extension').show();
		    $('#label_agent_user').show();

		    $('#label_extension_callback').hide();
		    $('#input_extension_callback').hide();
		    $('#label_password_callback').hide();
		    $('#input_password_callback').hide();
		}
    });
});

$(window).unload(function() {
	if (evtSource != null) {
		evtSource.close();
		evtSource = null;
	}
});

function apply_form_styles()
{
    $('#elastix-callcenter-cejillas-formulario').tabs();
    $('.elastix-callcenter-field-date').datepicker({
    	showOn:			'both',
    	buttonImage:	'images/calendar.gif',
    	buttonImageOnly: true,
    	showButtonPanel: true,
    	changeMonth:	true,
    	changeYear:		true,
    	dateFormat:		'yy-mm-dd'
    });
}

// Inicializar estado del cliente al refrescar la página
function initialize_client_state(nuevoEstado)
{
	estadoCliente.onhold = nuevoEstado.onhold;
	estadoCliente.break_id = nuevoEstado.break_id;
	estadoCliente.calltype = nuevoEstado.calltype;
	estadoCliente.campaign_id = nuevoEstado.campaign_id;
	estadoCliente.callid = nuevoEstado.callid;
	estadoCliente.waitingcall = nuevoEstado.waitingcall;

	// Lanzar el callback que actualiza el estado de la llamada
    setTimeout(do_checkstatus, 1);

    iniciar_cronometro((nuevoEstado.timer_seconds !== '') ? nuevoEstado.timer_seconds : null);
    abrir_url_externo(nuevoEstado.urlopentype, nuevoEstado.url);
}

// Inicializar el cronómetro con el valor de segundos indicado
function iniciar_cronometro(timer_seconds)
{
	// Anular el estado anterior
	if (timer != null) {
		clearTimeout(timer);
		timer = null;
	}
	fechaInicio = null;
	$('#elastix-callcenter-cronometro').text('00:00:00');

	// Iniciar el estado nuevo, si es válido
	if (timer_seconds != null) {
		fechaInicio = new Date();
		fechaInicio.setTime(fechaInicio.getTime() - timer_seconds * 1000);
		timer = setTimeout(actualizar_cronometro, 1);
	}
}

// Cada 500 ms se llama a esta función para actualizar el cronómetro
function actualizar_cronometro()
{
	var fechaDiff = new Date();
	var msec = fechaDiff.getTime() - fechaInicio.getTime();
	var tiempo = [0, 0, 0];
	tiempo[0] = (msec - (msec % 1000)) / 1000;
	tiempo[1] = (tiempo[0] - (tiempo[0] % 60)) / 60;
	tiempo[0] %= 60;
	tiempo[2] = (tiempo[1] - (tiempo[1] % 60)) / 60;
	tiempo[1] %= 60;
	var i = 0;
	for (i = 0; i < 3; i++) { if (tiempo[i] <= 9) tiempo[i] = "0" + tiempo[i]; }
	$('#elastix-callcenter-cronometro').text(tiempo[2] + ':' + tiempo[1] + ':' + tiempo[0]);
	timer = setTimeout(actualizar_cronometro, 500);
}

// El siguiente código aplica estilos de jQueryUI
function apply_ui_styles(uidata)
{
    if (uidata.no_call) {
        $('#btn_hangup').button('disable');
        $('#btn_transfer').button('disable');

        /* Esta llamada generalmente se realiza cuando el agente recién carga
         * la consola y no ha recibido una llamada todavía. Se debe de modificar
         * si se requiere que el agente recargue frecuentemente la consola y
         * preserve el hecho de que atendió previamente una llamada en la misma
         * sesión. */
        $('#btn_agendar_llamada').button('disable');
    }
    if (!uidata.can_confirm_contact) {
    	$('#btn_confirmar_contacto').button('disable');
    }
    if (!uidata.can_save_formdata) {
        $('#btn_guardar_formularios').button('disable');
    }
    schedule_call_error_msg_missing_date = uidata.schedule_call_error_msg_missing_date;
    $('#elastix-callcenter-seleccion-break').dialog({
        autoOpen: false,
        width: 300,
        height: 150,
        modal: true,
        buttons: [
            {
                text: uidata['break_commit'],
                click: function() { do_break(); $(this).dialog('close'); }
            },
            {
                text: uidata['break_dismiss'],
                click: function() { $(this).dialog('close'); }
            }
        ]
    });
    $('#elastix-callcenter-seleccion-transfer').dialog({
        autoOpen: false,
        width: 400,
        height: 200,
        modal: true,
        buttons: [
            {
                text: uidata['transfer_commit'],
                click: function() { do_transfer(); $(this).dialog('close'); }
            },
            {
                text: uidata['transfer_dismiss'],
                click: function() { $(this).dialog('close'); }
            }
        ]
    });
    $('#elastix-callcenter-agendar-llamada').dialog({
        autoOpen: false,
        width: 700,
        height: 350,
        modal: true,
        buttons: [
            {
                text: uidata['schedule_commit'],
                click: function() { if (do_schedule()) { $(this).dialog('close'); }}
            },
            {
                text: uidata['schedule_dismiss'],
                click: function() { $(this).dialog('close'); }
            }
        ]
    });

    externalurl_title = uidata['external_url_tab'];
}

// Redireccionar la página entera en caso de que la sesión se haya perdido
function verificar_error_session(respuesta)
{
	if (respuesta['statusResponse'] == 'ERROR_SESSION') {
		if (respuesta['error'] != null && respuesta['error'] != '')
			alert(respuesta['error']);
		window.open('index.php', '_self');
	}
}

// El siguiente código se ejecutará cuando se presione el botón de login del agente
function do_login()
{
	$.post('index.php?menu=' + module_name + '&rawmode=yes', {
        menu:		module_name,
        rawmode:	'yes',
        action:		'doLogin',
        agent:		$('#input_agent_user').val(),
        ext:		$('#input_extension').val(),
        ext_callback: 	$('#input_extension_callback').val(),
        pass_callback: 	$('#input_password_callback').val(),
        callback:	$('#input_callback').is(':checked')
	},
	function(respuesta) {
		verificar_error_session(respuesta);
        if (respuesta['status']) {
            // Se inicia la espera del login del agente
            login_estado_espera(respuesta['message']);
            setTimeout('do_checklogin()', 1);
        } else {
            // Ha ocurrido un error
            login_estado_error(respuesta['message']);
        }
	}, 'json')
	.fail(function() {
		login_estado_error('Failed to connect to server for agent login!');
	});
}

// El siguiente código se ejecuta al presionar el botón de colgado
function do_hangup()
{
	$('#btn_hangup').button('disable');
	$.post('index.php?menu=' + module_name + '&rawmode=yes', {
		menu:		module_name,
		rawmode:	'yes',
		action:		'hangup'
	},
	function (respuesta) {
		verificar_error_session(respuesta);
        if (respuesta['action'] == 'error') {
        	mostrar_mensaje_error(respuesta['message']);
        	if (estadoCliente.campaign_id != null || estadoCliente.waitingcall)
                $('#btn_hangup').button('enable');
        }

        // El cambio de estado de la interfaz se delega a la revisión
        // periódica del estado del agente.
	}, 'json')
	.fail(function() {
		mostrar_mensaje_error('Failed to connect to server to run request!');
	});
}

// Función que verifica si se ha completado el proceso de login
function do_checklogin()
{
	$.post('index.php?menu=' + module_name + '&rawmode=yes', {
			menu:		module_name,
			rawmode:	'yes',
			action:		'checkLogin'
		},
		function (respuesta) {
			verificar_error_session(respuesta);
	        if (respuesta['action'] == 'error') {
	            // El login ha concluido con un error
	            login_estado_error(respuesta['message']);
	        }
	        if (respuesta['action'] == 'wait') {
	            // Todavía no se termina proceso login, se espera
	            setTimeout('do_checklogin()', 1);
	        }
	        if (respuesta['action'] == 'login') {
	            // Login de agente ha tenido éxito, se refresca para mostrar formulario
	            window.open('index.php?menu=' + module_name, "_self");
	        }
    	}, 'json')
    	.fail(function() {
    		login_estado_error('Failed to connect to server to check for agent login!');
    	});
}

// El siguiente código se ejecuta al presionar el botón de fin de sesión
function do_logout()
{
    $.post('index.php?menu=' + module_name + '&rawmode=yes', {
		menu:		module_name,
		rawmode:	'yes',
		action:		'agentLogout'
	},
	function (respuesta) {
		verificar_error_session(respuesta);
        if (respuesta['action'] == 'error') {
        	mostrar_mensaje_error(respuesta['message']);
        }

        // Se asume que a pesar del error, el agente está deslogoneado
        window.open('index.php?menu=' + module_name, "_self");
	}, 'json')
	.fail(function() {
		mostrar_mensaje_error('Failed to connect to server to run request!');
	});
}

// Cambiar el mensaje de login al estado de espera
function login_estado_espera(msg)
{
    $('#login_icono_espera').attr("style", "visibility: visible; position: none;");
    $('#login_fila_estado').attr("style", "visibility: visible; position: none;");
    $('#login_msg_espera').text(msg);
    $('#login_msg_error').text("");
}

// Cambiar el mensaje de login al estado de error
function login_estado_error(msg)
{
    $('#login_icono_espera').attr("style", "visibility: hidden; position: absolute;");
    $('#login_fila_estado').attr("style", "visibility: visible; position: none;");
    $('#login_msg_espera').text("");
    $('#login_msg_error').text(msg);
}

// Cambiar el mensaje de login al estado ocioso
function login_estado_ocioso()
{
    $('#login_icono_espera').attr("style", "visibility: hidden; position: absolute;");
    $('#login_fila_estado').attr("style", "visibility: hidden; position: absolute;");
    $('#login_msg_espera').text("");
    $('#login_msg_error').text("");
}

function do_break()
{
	$.post('index.php?menu=' + module_name + '&rawmode=yes', {
		menu:		module_name,
		rawmode:	'yes',
		action:		'break',
		breakid:	$('#break_select').val()
	},
	function (respuesta) {
		verificar_error_session(respuesta);
        if (respuesta['action'] == 'error') {
        	mostrar_mensaje_error(respuesta['message']);
        }

        // El cambio de estado de la interfaz se delega a la revisión
        // periódica del estado del agente.
        // TODO: definir evento agentbreakenter y agentbreakexit
	}, 'json')
	.fail(function() {
		mostrar_mensaje_error('Failed to connect to server to run request!');
	});
}

function do_unbreak()
{
	// Botón está en estado de quitar break
    $.post('index.php?menu=' + module_name + '&rawmode=yes', {
		menu:		module_name,
		rawmode:	'yes',
		action:		'unbreak'
	},
	function (respuesta) {
		verificar_error_session(respuesta);
        if (respuesta['action'] == 'error') {
        	mostrar_mensaje_error(respuesta['message']);
        }

        // El cambio de estado de la interfaz se delega a la revisión
        // periódica del estado del agente.
        // TODO: definir evento agentbreakenter y agentbreakexit
	}, 'json')
	.fail(function() {
		mostrar_mensaje_error('Failed to connect to server to run request!');
	});
}

function do_transfer()
{
	$.post('index.php?menu=' + module_name + '&rawmode=yes', {
		menu:		module_name,
		rawmode:	'yes',
		action:		'transfer',
		extension:	$('#transfer_extension').val(),
		atxfer: 	$('#transfer_type_attended').is(':checked')
	},
	function (respuesta) {
		verificar_error_session(respuesta);
        if (respuesta['action'] == 'error') {
        	mostrar_mensaje_error(respuesta['message']);
        }

        // El cambio de estado de la interfaz se delega a la revisión
        // periódica del estado del agente.
	}, 'json')
	.fail(function() {
		mostrar_mensaje_error('Failed to connect to server to run request!');
	});
}

function do_confirm_contact()
{
	$.post('index.php?menu=' + module_name + '&rawmode=yes', {
		menu:		module_name,
		rawmode:	'yes',
		action:		'confirm_contact',
		id_contact:	$('#llamada_entrante_contacto_id').val()
	},
	function (respuesta) {
		verificar_error_session(respuesta);
        if (respuesta['action'] == 'error') {
        	mostrar_mensaje_error(respuesta['message']);
        } else {
        	mostrar_mensaje_info(respuesta['message']);
        }

	}, 'json')
	.fail(function() {
		mostrar_mensaje_error('Failed to connect to server to run request!');
	});
}

function do_schedule()
{
	// Verificar que se ha elegido realmente una fecha
	if ($('#schedule_type_bydate').is(':checked') &&
		($('#schedule_date_start').datepicker('getDate') == null || $('#schedule_date_end').datepicker('getDate') == null )) {

		$('#elastix-callcenter-agendar-llamada-error-message-text').text(schedule_call_error_msg_missing_date);
		$('#elastix-callcenter-agendar-llamada-error-message').show('slow', 'linear', function() {
			setTimeout(function() {
				$('#elastix-callcenter-agendar-llamada-error-message').fadeOut();
			}, 5000);
		});
		return false;
	}
	$.post('index.php?menu=' + module_name + '&rawmode=yes', {
		menu:		module_name,
		rawmode:	'yes',
		action:		'schedule',
		data:		{
			schedule_new_phone:		$('#schedule_new_phone').val(),
			schedule_new_name:		$('#schedule_new_name').val(),
			schedule_use_daterange:	$('#schedule_type_bydate').is(':checked'),
			schedule_use_sameagent:	$('#schedule_same_agent').is(':checked'),
			schedule_date_start:	$('#schedule_date_start').val(),	// Asume yyyy-mm-dd
			schedule_date_end:		$('#schedule_date_end').val(),		// Asume yyyy-mm-dd
			schedule_time_start:	$('#schedule_time_start_hh').val() + ':' + $('#schedule_time_start_mm').val() + ':00',
			schedule_time_end:		$('#schedule_time_end_hh').val() + ':' + $('#schedule_time_end_mm').val() + ':59'
		}
	},
	function (respuesta) {
		verificar_error_session(respuesta);
        if (respuesta['action'] == 'error') {
        	mostrar_mensaje_error(respuesta['message']);
        } else {
        	mostrar_mensaje_info(respuesta['message']);
        }

	}, 'json')
	.fail(function() {
		mostrar_mensaje_error('Failed to connect to server to run request!');
	});
	return true;
}

function do_save_forms()
{
	$.post('index.php?menu=' + module_name + '&rawmode=yes', {
		menu:		module_name,
		rawmode:	'yes',
		action:		'saveforms',
		data:		$('.elastix-callcenter-field').map(function() {
						return [[this.id, $(this).val()]];
					}).get()
	},
	function (respuesta) {
		verificar_error_session(respuesta);
        if (respuesta['action'] == 'error') {
        	mostrar_mensaje_error(respuesta['message']);
        } else {
        	mostrar_mensaje_info(respuesta['message']);
        }

	}, 'json')
	.fail(function() {
		mostrar_mensaje_error('Failed to connect to server to run request!');
	});
}

function do_ping()
{
	$.get('index.php', {menu: module_name, action: 'ping', rawmode: 'yes'}, function (respuesta) {
		verificar_error_session(respuesta);
		setTimeout(do_ping, (respuesta['gc_maxlifetime'] / 2) * 1000);
	});
}

function do_checkstatus()
{
	params = {
		menu:		module_name,
		rawmode:	'yes',
		action:		'checkStatus',
		clientstate: estadoCliente
	};
	if (window.EventSource) {
		params['serverevents'] = true;
		evtSource = new EventSource('index.php?' + $.param(params));
		evtSource.onmessage = function(event) {
			manejarRespuestaStatus($.parseJSON(event.data));
		}
		evtSource.onerror = function(event) {
			mostrar_mensaje_error('Lost connection to server (SSE), retrying...');
		}

		// Iniciar el ping de inmediato
		setTimeout(do_ping, 1);
	} else {
		$.post('index.php?menu=' + module_name + '&rawmode=yes', params,
		function (respuesta) {
			verificar_error_session(respuesta);
			manejarRespuestaStatus(respuesta);

			// Lanzar el método de inmediato
			setTimeout(do_checkstatus, 1);
		}, 'json').fail(function() {
			mostrar_mensaje_error('Lost connection to server (Long-Polling), retrying...');
			setTimeout(do_checkstatus, 5000);
		});
	}
}

function manejarRespuestaStatus(respuesta)
{
	for (var i in respuesta) {
		if (respuesta[i].txt_estado_agente_inicial != null)
			$('#elastix-callcenter-estado-agente-texto').text(respuesta[i].txt_estado_agente_inicial);
		if (respuesta[i].class_estado_agente_inicial != null)
			$('#elastix-callcenter-estado-agente')
				.removeClass('elastix-callcenter-class-estado-ocioso')
				.removeClass('elastix-callcenter-class-estado-break')
				.removeClass('elastix-callcenter-class-estado-activo')
				.removeClass('elastix-callcenter-class-estado-esperando')
				.addClass(respuesta[i].class_estado_agente_inicial);
		if (respuesta[i].timer_seconds != null) {
			if (respuesta[i].timer_seconds !== '') {
				iniciar_cronometro(respuesta[i].timer_seconds);
			} else {
				iniciar_cronometro(null);
			}
		}

		switch (respuesta[i].event) {
		case 'logged-out':
			// El refresco debería conducir a la página de login
			window.open('index.php?menu=' + module_name, "_self");
			return;
		case 'breakenter':
			// El agente ha entrado en break
			estadoCliente.break_id = respuesta[i].break_id;
			$('#btn_togglebreak')
				.removeClass('elastix-callcenter-boton-break')
				.addClass('elastix-callcenter-boton-unbreak')
				.children('span').text(respuesta[i].txt_btn_break);
			break;
		case 'breakexit':
			// El agente ha salido del break
			estadoCliente.break_id = null;
			$('#btn_togglebreak')
				.removeClass('elastix-callcenter-boton-unbreak')
				.addClass('elastix-callcenter-boton-break')
				.children('span').text(respuesta[i].txt_btn_break);
			break;
		case 'holdenter':
			estadoCliente.onhold = true;
			// TODO
			break;
		case 'holdexit':
			estadoCliente.onhold = false;
			// TODO
			break;
		case 'agentlinked':
			// El agente ha recibido una llamada
			estadoCliente.calltype = respuesta[i].calltype;
			estadoCliente.campaign_id = respuesta[i].campaign_id;
			estadoCliente.callid = respuesta[i].callid;
			$('#btn_hangup').button('enable');
			$('#btn_transfer').button('enable');
			$('#elastix-callcenter-cronometro').text(respuesta[i].cronometro);
			$('#elastix-callcenter-llamada-info')
			    .css('color', '')
				.empty()
				.append(respuesta[i].llamada_informacion);
			$('#elastix-callcenter-llamada-script')
				.empty()
				.append(respuesta[i].llamada_script);
			$('#elastix-callcenter-llamada-form')
				.empty()
				.append(respuesta[i].llamada_formulario);
			$('#llamada_entrante_contacto_telefono, #llamada_saliente_contacto_telefono')
				.text(respuesta[i].txt_contacto_telefono);
			$('#schedule_new_phone').val(respuesta[i].txt_contacto_telefono);

			// Preparar y mostrar la barra correspondiente
			if (respuesta[i].calltype == 'incoming') {
				$('#btn_confirmar_contacto').button();
				if (respuesta[i].puede_confirmar_contacto)
					$('#btn_confirmar_contacto').button('enable');
				else $('#btn_confirmar_contacto').button('disable');
				$('#btn_confirmar_contacto').click(do_confirm_contact);
			} else if (respuesta[i].calltype == 'outgoing') {
				$('#llamada_saliente_nombres').text(respuesta[i].txt_contacto_nombres);
				$('#schedule_new_name').val(respuesta[i].txt_contacto_nombres);
				$('#btn_agendar_llamada').button('enable');
			}

			apply_form_styles();
		    $('#btn_guardar_formularios').button('enable');
			abrir_url_externo(respuesta[i].urlopentype, respuesta[i].url);
			break;
		case 'agentunlinked':
	        // El agente se ha desconectado de la llamada
		    var l_calltype = estadoCliente.calltype;
		    var l_campaign_id = estadoCliente.campaign_id;
			estadoCliente.calltype = null;
			estadoCliente.campaign_id = null;
			estadoCliente.callid = null;

			$('#btn_hangup').button('disable');
	        $('#btn_transfer').button('disable');
	        if (l_calltype == 'incoming') {
	            $('#btn_agendar_llamada').button('disable');
	        }
	        $('#elastix-callcenter-cronometro').text('00:00:00');

	        // Vaciar las áreas para la llamada
			$('#elastix-callcenter-llamada-script').empty();
			$('#elastix-callcenter-llamada-info').css("color", "#778899");
			break;
		case 'waitingenter':
			estadoCliente.waitingcall = true;
			break;
		case 'waitingexit':
			estadoCliente.waitingcall = false;
			break;
		}
	}
}

function mostrar_mensaje_info(s)
{
	$('#elastix-callcenter-info-message-text').text(s);
	$('#elastix-callcenter-info-message').show('slow', 'linear', function() {
		setTimeout(function() {
			$('#elastix-callcenter-info-message').fadeOut();
		}, 5000);
	});
}

function mostrar_mensaje_error(s)
{
	$('#elastix-callcenter-error-message-text').text(s);
	$('#elastix-callcenter-error-message').show('slow', 'linear', function() {
		setTimeout(function() {
			$('#elastix-callcenter-error-message').fadeOut();
		}, 5000);
	});
}

function abrir_url_externo(urlopentype, url)
{
	if (urlopentype != null) {
		switch (urlopentype) {
		case 'iframe':
			if (jqueryui_tabs_use_refresh) {
			    // Se quita la cejilla anterior. Se asume que se fue marcada con clase .externalurl
		    	$('#elastix-callcenter-cejillas-contenido').find('.ui-tabs-nav li.tab-externalurl').remove();
			    $('#tabs-externalurl').remove();

			    // Se agrega la nueva cejilla, si existe
			    if (url != null) {
			        $('#elastix-callcenter-cejillas-contenido').append(
			            '<div id="tabs-externalurl"><iframe scrolling="auto" height="450" frameborder="0" width="100%" src="' + url + '" /></div>');
			        $('<li class="tab-externalurl"><a href="#tabs-externalurl">'+externalurl_title+'</a></li>')
			            .appendTo('#elastix-callcenter-cejillas-contenido > .ui-tabs-nav');
			    }

			    // Aplicar cambios
			    $('#elastix-callcenter-cejillas-contenido').tabs('refresh');
		    } else {
                externalurl = url;
                $('#elastix-callcenter-cejillas-contenido').tabs('remove', '#tabs-externalurl');
                $('#elastix-callcenter-cejillas-contenido').tabs('add', '#tabs-externalurl', externalurl_title);
		    }
			break;
		case 'jsonp':
			$.ajax(url, {
				dataType: 'jsonp',
				context:	document
			});
			break;
		case 'window':
		default:
			window.open(url, '_blank');
			break;
		}
	}
}