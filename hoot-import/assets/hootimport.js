jQuery(document).ready(function($) {
	"use strict";

	if( 'undefined' == typeof hootimportData )
		window.hootimportData = {};

	var hootImportDirtyVals = false,
		activeProcess = false;
	window.onbeforeunload = function(e) {
		if ( hootImportDirtyVals ) {
			e.preventDefault();
			e.returnValue = 'The content is being imported. Please wait for the process to finish.';
		}
	};

	/*** Modules Toggle ***/
	$('.hootimp-opbox').not('.hootimp-opbox--plugin_active, .hootimp-opbox--plugin_reqd').each( function() {
		var $this = $(this),
			$toggle = $this.find('.hootimp-toggle'),
			$checkbox = $this.find('input[type=checkbox]');
		$toggle.on( 'click', function(e) {
			e.preventDefault();
			if ( $checkbox.is(':checked') ) {
				$checkbox.prop('checked', false);
				$this.addClass('hootimp-opbox--plugin_noaction');
				if ( $checkbox.val() === 'woocommerce' ) {
					$('#hootimp-form').addClass('hootimp--nowc');
					$('input[value="wcxml"]').prop("checked", false);
				}
			} else {
				$checkbox.prop('checked', true);
				$this.removeClass('hootimp-opbox--plugin_noaction');
				if ( $checkbox.val() === 'woocommerce' ) {
					$('#hootimp-form').removeClass('hootimp--nowc');
					$('input[value="wcxml"]').prop("checked", true);
				}
			}
		} );
	} );

	/*** Submit ***/

	$('#hootimp-submit').click( function(e){
		e.preventDefault();

		var $submit = $(this),
			$form = $('#hootimp-form');

		if ( $submit.is('.disabled') )
			return;
		if ( activeProcess ) { // This should not happen as no access point to Submit button once started
			alert( hootimportData.strings.active_process_alert );
			return;
		}

		$form.removeClass('hootimp-formcomplete hootimp-formloaderror')
		var processimport = function() {
			// Setup
			console.log( 'Start Import Process' );
			$form.addClass('hootimp-formloader');
			$submit.addClass('disabled');
			hootImportDirtyVals = true;
			activeProcess = true;

			var pack      = $form.find('input[name="pack"]').val();
			var demoslug  = $form.find('input[name="demo"]').val();
			var contentTypes = [ 'xml', 'wcxml', 'dat', 'wie' ];
			// Create selected list to be processed
			var selected = [ {
				name: 'Fetching Files',
				type: 'prepare',
				demoslug: demoslug,
				pack: pack,
				subroutines: [],
			} ];
			// Order of selected actions is important
			$form.find('input[name="plugin[]"]:checked').each( function() {
				var plugin = $.extend( {
					type: 'plugin',
					value: $(this).val(),
					demoslug: demoslug
				}, $(this).data() );
				selected.push( plugin );
				selected[0]['subroutines'].push( plugin.value );
			} );
			contentTypes.forEach( function( contentType ) {
				$form.find('input[value="' + contentType + '"]:checked').each( function() {
					var content = $.extend( {
						type: 'content',
						value: $(this).val(),
						demoslug: demoslug,
						pack: pack
					}, $(this).data() );
					selected.push( content );
					selected[0]['subroutines'].push( content.value );
				} );
			} );
			selected.push( {
				name: 'Finalizing Settings',
				type: 'final',
				demoslug: demoslug,
				pack: pack
			} );

			var $loadermsg = $('#hootimp-loadermsg'),
				$loaderbar = $('.hootimp-loaderbar div'),
				waitmsgInterval, waitmsgAdded = 0,
				xmlretry = 0,
				errList = [],
				msgList = [],
				steps = selected.length,
				step = 1;

			// Process selected
			var processNext = function( selected, index, callback ) {
				if (index >= selected.length) {
					if (callback) callback();
					return;
				}
				var mod = selected[index];
				var lmsg = '';
					lmsg += '<span>' + hootimportData.strings.loading_step + ' ' + step + ' / ' + (steps - 0) + ' :</span> ';
					if ( mod.type === 'plugin' ) {
						lmsg += '<em>' + hootimportData.strings.loading_plugin + ' ' + mod.name + '</em>';
					} else if ( mod.type === 'prepare' ) {
						lmsg += '<em>' + hootimportData.strings.loading_prepare + '</em>';
					} else if ( mod.type === 'final' ) {
						lmsg += '<em>' + hootimportData.strings.loading_final + '</em>';
					} else {
						lmsg += '<em>' + hootimportData.strings.loading_content + ' ' + mod.name + '</em>';
					}
					if ( mod.value === 'xml' ) {
						lmsg += ' <strong>' + hootimportData.strings.loading_xml + '</strong>';
						var xmlmsg = ' <strong><span class="dashicons dashicons-update"></span>STATUS UPDATE: <span style="font-weight:normal">' + hootimportData.strings.stillloading_xml + '</span></strong>'
						if ( xmlretry ) {
							lmsg += xmlmsg
						} else {
							waitmsgInterval = setInterval( function() {
								if ( !waitmsgAdded ) {
									$loadermsg.html( lmsg + xmlmsg );
									waitmsgAdded = 1;
								}
							}, 60000 );
						}
					}
				$loadermsg.html( lmsg );
				$loaderbar.css( 'width', ( step / steps * 100 ) + '%' );
				$.ajax( {
					url: ajaxurl,
					type : 'post',
					data: {
						'action': hootimportData.import_action,
						'nonce': hootimportData.nonce,
						'mods': mod.type === 'final' ? JSON.stringify( selected ) : JSON.stringify( [] ),
						'mod': JSON.stringify( mod )
					},
					success: function( response ){
						xmlretry = 0;
						if ( response.error ) {
							console.log( 'AJAX response Error ' + mod.name, response );
							var errorMsg = typeof response.error === 'string' ? response.error : 'Unknown Error';
							errList.push( '<strong>' + mod.name + '</strong> ' + errorMsg );
							msgList.push( '<h3>' + mod.name + '</h3>' + errorMsg );
						} else {
							console.log( 'AJAX Success ' + mod.name, response );
							if ( typeof response.success === 'string' ) {
								msgList.push( '<h3>' + mod.name + '</h3>' + response.success );
							}
						}
					},
					error: function( xhr, textStatus, errorThrown ) {
						var msg = '';
						if ( typeof xhr.status === 'string' || typeof xhr.status === 'number' ) msg += xhr.status + ' : ';
						if ( typeof xhr.statusText === 'string' ) msg += xhr.statusText;
						else if ( typeof errorThrown === 'string' ) msg += errorThrown;
						msgList.push( '<h3>' + mod.name + '</h3>' + msg );
						console.log( 'AJAX Error ' + mod.name, textStatus, errorThrown );
						if ( ( mod.value === 'xml' || mod.value === 'wcxml' ) && !xmlretry ) {
							xmlretry = 1;
							var xmlmsg = '<span style="color:#d63638">Server timeout in first attempt. Giving it one more try.<br />You may need to increase <strong>"max_execution_time"</strong> in your php.ini configuration file.</span>';
							msgList.push( xmlmsg );
							console.log( xmlmsg );
						} else {
							xmlretry = 0;
							if ( msg === '500 : Internal Server Error' ) {
								msg = '500 : Server Timeout : Please try again.';
							}
							errList.push( '<strong>' + mod.name + '</strong> ' + msg );
						}
					},
					complete: function( data ){
						if ( xmlretry ) {
							processNext( selected, index, callback );
						} else {
							if ( waitmsgInterval ) clearInterval( waitmsgInterval );
							waitmsgAdded = 0;
							step++;
							processNext( selected, index + 1, callback );
						}
					}
				} );
			}
			processNext( selected, 0, function() {
				// Reverse Setup
				activeProcess = false;
				hootImportDirtyVals = false;
				$submit.removeClass('disabled');
				$form.removeClass('hootimp-formloader');
				if ( errList.length > 0 ) {
					$form.addClass('hootimp-formloaderror');
					$('#hootimp-loaderror-details').html( '<p>' + errList.join('<br>') + '</p>' );
				} else {
					$form.addClass('hootimp-formcomplete');
				}
				if ( msgList.length > 0 ) {
					$('.hootimp-load-details').html( msgList.map( function(msg) {
						return '<pre>' + $('<textarea/>').html(msg).text() + '</pre>';
					} ).join('<hr>') );
				}
				console.log( 'Import Process complete' );
			} );

		}

		$.confirm({
			title : '',
			content: hootimportData.strings.confirm_msg,
			boxWidth: '50%',
			useBootstrap: false,
			backgroundDismiss: true,
			animation: 'scale',
			closeAnimation: 'scale',
			onContentReady: function () { $( 'body' ).addClass( 'hootimp-message-popup' ); },
			onDestroy: function () { $( 'body' ).removeClass( 'hootimp-message-popup' ); },
			buttons: {
				confirm: {
					text: hootimportData.strings.confirm_primarybtn,
					btnClass: 'button button-primary',
					keys: ['enter'],
					action: processimport
				},
				cancel: {
					text: hootimportData.strings.confirm_cancelbtn,
					btnClass: 'button',
					action: function(){}
				},
			}
		});

	});

	/*** Log ***/

	$('.hootimp-show-log').click( function(e){
		e.preventDefault();
		$.confirm({
			title : '',
			content: $('.hootimp-load-details').html(),
			boxWidth: '50%',
			useBootstrap: false,
			backgroundDismiss: true,
			animation: 'scale',
			closeAnimation: 'scale',
			onContentReady: function () { $( 'body' ).addClass( 'hootimp-log-popup' ); },
			onDestroy: function () { $( 'body' ).removeClass( 'hootimp-log-popup' ); },
			buttons: {
				cancel: {
					text: 'Close',
					btnClass: 'button',
					action: function(){}
				},
			}
		});
	});

	/*** Admin Footer ***/

	$('.hootimp-rateus').click( function(e){
		e.preventDefault();
		var url      = $(this).attr('href') ,
			doneText = $(this).data('rated');
		window.open( url, '_blank' );
		$('#footer-left').text( doneText );
		$.post(
			ajaxurl,
			{ action: 'hootimport_rated', nonce: hootimportData.nonce, }
		);
	});

});