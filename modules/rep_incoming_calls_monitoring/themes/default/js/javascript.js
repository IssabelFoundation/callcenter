var module_name = 'rep_incoming_calls_monitoring';

/* El siguiente objeto es el estado de la interfaz del CallCenter. Al comparar
 * este objeto con los cambios de estado producto de eventos del ECCP, se 
 * consigue detectar los cambios requeridos a la interfaz sin tener que recurrir
 * a llamadas repetidas al servidor.
 * Este objeto se inicializa en initialize_client_state() */
var estadoCliente = null;
var estadoClienteHash = null;

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
	
	// Lanzar el callback que actualiza el estado de la llamada
    setTimeout(do_checkstatus, 1);
}

$(window).unload(function() {
	if (evtSource != null) {
		evtSource.close();
		evtSource = null;
	}
});

function do_checkstatus()
{
	var params = {
			menu:		module_name, 
			rawmode:	'yes',
			action:		'checkStatus',
			clientstatehash: estadoClienteHash
		};

	if (window.EventSource) {
		params['serverevents'] = true;
		evtSource = new EventSource('index.php?' + $.param(params));
		evtSource.onmessage = function(event) {
			manejarRespuestaStatus($.parseJSON(event.data));
		}
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
	// Intentar recargar la página en caso de error
	if (respuesta['error'] != null) {
		window.alert(respuesta['error']);
		location.reload();
		return;
	}
	
	var actualizado = false;
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
			estadoCliente[k] = respuesta[k];
			actualizado = true;
			
			// Actualizar la fila de la cola correspondiente
			for (var kstat in estadoCliente[k]) {
				$('#' + k + '-' + kstat).text(estadoCliente[k][kstat]);
			}
			
		} else {
			// TODO: no se maneja todavía aparición de nueva cola
		}
	}

	// Actualizar la fila de los totales
	if (actualizado) {
		var totales = {};
		for (var kqueue in estadoCliente) {
			for (var kstat in estadoCliente[kqueue]) {
				if (totales[kstat] == null) totales[kstat] = 0;
				totales[kstat] += estadoCliente[kqueue][kstat];
			}
		}
		
		for (var kstat in totales) {
			$('#total-' + kstat).text(totales[kstat]);
		}
	}
}