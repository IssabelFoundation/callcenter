var module_name = 'rep_agents_monitoring';

/* El siguiente objeto es el estado de la interfaz del CallCenter. Al comparar
 * este objeto con los cambios de estado producto de eventos del ECCP, se
 * consigue detectar los cambios requeridos a la interfaz sin tener que recurrir
 * a llamadas repetidas al servidor.
 * Este objeto se inicializa en initialize_client_state() */
var estadoCliente = null;
var estadoClienteHash = null;

// Objeto de timer para el cronómetro
var timer = null;

//Objeto EventSource, si está soportado por el navegador
var evtSource = null;

//Redireccionar la página entera en caso de que la sesión se haya perdido
function verificar_error_session(respuesta)
{
	if (respuesta['statusResponse'] == 'ERROR_SESSION') {
		if (respuesta['error'] != null && respuesta['error'] != '')
			alert(respuesta['error']);
		window.open('index.php', '_self');
	}
}

//Inicializar estado del cliente al refrescar la página
function initialize_client_state(nuevoEstado, nuevoEstadoHash)
{
	estadoCliente = nuevoEstado;
	estadoClienteHash = nuevoEstadoHash;

	var fechaInicio = new Date();
	var regexp = /^(queue-\d+)/;
	for (var k in estadoCliente) {
		var keys = ['sec_laststatus', 'logintime', 'sec_calls'];
		for (var j = 0; j < keys.length; j++) {
			var ktimestamp = keys[j];
			estadoCliente[k]['orig_'+ktimestamp] = estadoCliente[k][ktimestamp];
			if (estadoCliente[k][ktimestamp] != null) {
				var d = new Date();
				d.setTime(fechaInicio.getTime() - estadoCliente[k][ktimestamp] * 1000);
				estadoCliente[k][ktimestamp] = d;
			}
		}

		// Registrar a qué total de cola contribuye esta fila
		var kq = regexp.exec(k);
		estadoCliente[k]['queuetotal'] = null;
		if (kq != null) {
			estadoCliente[k]['queuetotal'] = kq[1];
		}
	}

	// Lanzar el callback que actualiza el estado de la llamada
    setTimeout(do_checkstatus, 1);

	timer = setTimeout(actualizar_cronometro, 1);
}

$(window).unload(function() {
	if (evtSource != null) {
		evtSource.close();
		evtSource = null;
	}
});

//Cada 500 ms se llama a esta función para actualizar el cronómetro
function actualizar_cronometro()
{
	actualizar_valores_cronometro();
	timer = setTimeout(actualizar_cronometro, 500);
}

function actualizar_valores_cronometro()
{
	var totalesCola = {};
	for (var k in estadoCliente) {
		var kq = estadoCliente[k]['queuetotal'];
		if (totalesCola[kq] == null) {
			// Estos totales son en milisegundos
			totalesCola[kq] = {
				logintime: 0,
				sec_calls: 0
			};
		}

		// El último estado se actualiza si el tiempo no es nulo
		if (estadoCliente[k]['sec_laststatus'] != null) {
			formatoCronometro('#'+k+'-sec_laststatus', estadoCliente[k]['sec_laststatus']);
		}

		// El tiempo total de login se actualiza si el estado no es offline
		if (estadoCliente[k]['status'] != 'offline') {
			totalesCola[kq]['logintime'] += formatoCronometro('#'+k+'-logintime', estadoCliente[k]['logintime']);
		} else {
			totalesCola[kq]['logintime'] += estadoCliente[k]['orig_logintime'] * 1000;
		}

		// El tiempo total de llamadas se actualiza si el estado es oncall y si
		// está activa la bandera oncallupdate
		if (estadoCliente[k]['status'] == 'oncall' && estadoCliente[k]['oncallupdate']) {
			totalesCola[kq]['sec_calls'] += formatoCronometro('#'+k+'-sec_calls', estadoCliente[k]['sec_calls']);
		} else {
			totalesCola[kq]['sec_calls'] += estadoCliente[k]['orig_sec_calls'] * 1000;
		}
	}

	// Actualizar totales por cola
	for (var kq in totalesCola) {
		formatoMilisegundo('#'+kq+'-logintime', totalesCola[kq]['logintime']);
		formatoMilisegundo('#'+kq+'-sec_calls', totalesCola[kq]['sec_calls']);
	}
}

function formatoCronometro(selector, fechaInicio)
{
	var fechaDiff = new Date();
	var msec = fechaDiff.getTime() - fechaInicio.getTime();

	formatoMilisegundo(selector, msec);
	return msec;
}

function formatoMilisegundo(selector, msec)
{
	var tiempo = [0, 0, 0];
	tiempo[0] = (msec - (msec % 1000)) / 1000;
	tiempo[1] = (tiempo[0] - (tiempo[0] % 60)) / 60;
	tiempo[0] %= 60;
	tiempo[2] = (tiempo[1] - (tiempo[1] % 60)) / 60;
	tiempo[1] %= 60;
	var i = 0;
	for (i = 0; i < 3; i++) { if (tiempo[i] <= 9) tiempo[i] = "0" + tiempo[i]; }
	$(selector).text(tiempo[2] + ':' + tiempo[1] + ':' + tiempo[0]);
}

function do_checkstatus()
{
/*
	var clientstate = {};
	for (var k in estadoCliente) {
		clientstate[k] = {
			status:			estadoCliente[k]['status'],
			oncallupdate:	estadoCliente[k]['oncallupdate']
		};
	}
*/
	var params = {
			menu:		module_name,
			rawmode:	'yes',
			action:		'checkStatus',
			//clientstate: clientstate
			clientstatehash: estadoClienteHash
		};

	if (window.EventSource) {
		params['serverevents'] = true;
		evtSource = new EventSource('index.php?' + $.param(params));
		evtSource.onmessage = function(event) {
			manejarRespuestaStatus($.parseJSON(event.data));
		};
		evtSource.onerror = function(event) {
			/* NO QUIERO REINTENTOS EN CASO DE ERROR: para cuando se
			 * realiza el reintento, se lo hace con el mismo hash que
			 * se usó iniciamente, pero ese hash ya no es válido porque
			 * el estado es volátil. */
			event.target.close();
			setTimeout(function() { location.reload(); }, 3000);
		};
	} else {
		$.post('index.php?menu=' + module_name + '&rawmode=yes', params,
		function (respuesta) {
			verificar_error_session(respuesta);
			manejarRespuestaStatus(respuesta);

			// Lanzar el método de inmediato
			setTimeout(do_checkstatus, 1);
		}, 'json');
	}
}

function manejarRespuestaStatus(respuesta)
{
	var fechaInicio = new Date();
	var keys = ['sec_laststatus', 'logintime', 'sec_calls'];

	// Intentar recargar la página en caso de error
	if (respuesta['error'] != null) {
		window.alert(respuesta['error']);
		location.reload();
		return;
	}

	for (var k in respuesta) {
		if (k == 'estadoClienteHash') {
			// Caso especial - actualizar hash de estado
			if (respuesta[k] == 'mismatch') {
				// Ha ocurrido un error y se ha perdido sincronía
				location.reload();
				return;
			} else {
				estadoClienteHash = respuesta[k];
			}
		} else if (estadoCliente[k] != null) {
			if (estadoCliente[k]['status'] != respuesta[k]['status']) {
				// El estado del agente ha cambiado, actualizar icono
				var statuslabel = $('#'+k+'-statuslabel');
				statuslabel.empty();
				switch (respuesta[k]['status']) {
				case 'offline':
					statuslabel.text('LOGOUT'); // TODO: i18n
					break;
				case 'online':
					statuslabel.append('<img src="modules/'+module_name+'/images/ready.png" border="0" alt="'+'READY'+'"/>');
					break;
				case 'oncall':
					statuslabel.append('<img src="modules/'+module_name+'/images/call.png" border="0" alt="'+'CALL'+'"/>');
					break;
				case 'paused':
					statuslabel.append('<img src="modules/'+module_name+'/images/break.png" border="0" alt="'+'BREAK'+'"/>');
					if (typeof respuesta[k].pausename == 'string') statuslabel.append($('<span></span>').text(respuesta[k].pausename));
					break;
				}
				estadoCliente[k]['status'] = respuesta[k]['status'];
			}

			// Actualizar los cronómetros con los nuevos valores
			for (var j = 0; j < keys.length; j++) {
				var ktimestamp = keys[j];
				estadoCliente[k]['orig_'+ktimestamp] = respuesta[k][ktimestamp];
				if (respuesta[k][ktimestamp] == null) {
					estadoCliente[k][ktimestamp] = null;
					$('#'+k+'-'+ktimestamp).empty();
				} else {
					var d = new Date();
					d.setTime(fechaInicio.getTime() - respuesta[k][ktimestamp] * 1000);
					estadoCliente[k][ktimestamp] = d;
					formatoCronometro('#'+k+'-'+ktimestamp, estadoCliente[k][ktimestamp]);
				}
			}
			estadoCliente[k]['oncallupdate'] = respuesta[k]['oncallupdate'];
			estadoCliente[k]['num_calls'] = respuesta[k]['num_calls'];
			$('#'+k+'-num_calls').text(estadoCliente[k]['num_calls']);
		} else {
			// TODO: no se maneja todavía aparición de agente en nueva cola
		}
	}

	// Actualizar número de llamadas por cola
	var totalesCola = {};
	for (var k in estadoCliente) {
		var kq = estadoCliente[k]['queuetotal'];
		if (kq != null) {
			if (totalesCola[kq] == null) {
				totalesCola[kq] = estadoCliente[k]['num_calls'];
			} else {
				totalesCola[kq] += estadoCliente[k]['num_calls'];
			}
		}
	}
	for (var kq in totalesCola) {
		$('#'+kq+'-num_calls').text(totalesCola[kq]);
	}

	// Actualizar los totales de tiempo por cola
	actualizar_valores_cronometro();
}
