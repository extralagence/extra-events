(function ( $ ) {
	"use strict";

	$(function () {

		// Place your administration-specific JavaScript here
		var generateRequestSize = 10,
			zipRequestSize = 100,
			$buttons = $('.export-bill-button'),
			allBookingIdsByEventIds = exportBillOptions.allBookingIdsByEventIds;

		$buttons.on('click', function (event) {
			event.preventDefault();
			var $this = $(this),
				$bloc = $this.closest('.export-bill-bloc').addClass('processing'),
				eventId = $bloc.data('event-id'),
				$console = $('#export-bill-console-'+eventId),
				forceRefresh = $('#export-bill-force-refresh-'+eventId+':checked').length > 0;

			$buttons.addClass('disabled');
			$console.html('');
			$('#export-bill-bloc-'+eventId+' .export-bill-download-links').html('');

			generateBills(getEventById(eventId), 0, 0, $console, [], forceRefresh);
		});

		function getEventById (eventId) {
			var goodGuy = null,
				current = null,
				index = 0;

			while (index < allBookingIdsByEventIds.length && goodGuy === null) {
				current = allBookingIdsByEventIds[index];
				if (current.id == eventId) {
					goodGuy = current;
				}
				index++;
			}

			return goodGuy;
		}

		function generateBills(event, bookingIndex, nbGoodResult, $console, exportIds, forceRefresh) {
			var toSendIds = [];

			var i = 0,
				nextIndex = bookingIndex + i;
			while (i < generateRequestSize && event.bookingIds.length > nextIndex) {
				toSendIds.push(event.bookingIds[nextIndex]);
				i++;
				nextIndex = bookingIndex + i;
			}
			bookingIndex = bookingIndex + i;

			var data = {
				'action': 'generate_bills',
				'booking_ids': toSendIds,
				'force_refresh' : (forceRefresh) ? 1 : 0
			};

			$.post(exportBillOptions.ajaxUrl, data, function(responseString) {
				var responses = null;
				try {
					responses = $.parseJSON(responseString);
				} catch (e) {
					console.log(responseString);
					$console.append('<span class="export-bill-error">Impossible de générer les factures...</span><br />');

					finalCallback();
					return;
				}

				if (responses !== null) {
					var responseIndex = 0,
						response = null;
					while (responseIndex < responses.length) {
						response = responses[responseIndex++];
						if (response.success) {
							//$console.append('<span class="export-bill-success">Création de la facture pour '+response.personEmail+'</span><br />');
							exportIds.push(response.bookingId);
							nbGoodResult++;
						} else {
							$console.append('<span class="export-bill-error">Impossible de créer la facture pour : '+response.personEmail+'</span><br />');
						}
					}
					$console.append('<span class="export-bill-success">Création de '+nbGoodResult+'/'+event.bookingIds.length+' facture(s)</span><br />');

					if (event.bookingIds.length > bookingIndex) {
						// Launch next request with same event
						generateBills(event, bookingIndex, nbGoodResult, $console, exportIds, forceRefresh);
					} else {


						$console.append('<span class="export-bill-finished">Compression en archive zip</span><br />');

						generateArchive(event.id, exportIds, 0, [], $console);
					}
				}
			});
		}

		function generateArchive(eventId, exportIds, exportIndex, exportResults, $console) {
			var toSendIds = exportIds.slice(exportIndex, (exportIndex + zipRequestSize)),
				currentZip = Math.floor(exportIndex / zipRequestSize) + 1,
				totalZip = Math.ceil(exportIds.length / zipRequestSize);

			exportIndex += toSendIds.length;

			if (toSendIds.length == 0) {
				showExportResult(exportResults, $console, eventId);
			} else {
				var isFull = (exportIds.length <= zipRequestSize && exportIndex == 0),
					createLinkData = {
					'action': 'create_zip_bill',
					'booking_ids': toSendIds,
					'event_id': eventId,
					'full': (isFull)  ? '1' : '0',
					'current_zip' : currentZip,
					'total_zip' : totalZip
				};
				$.post(exportBillOptions.ajaxUrl, createLinkData, function(responseString) {
					var zipResponse = null;
					try {
						zipResponse = $.parseJSON(responseString);
					} catch (e) {
						console.log(responseString);
						$console.append('<span class="export-bill-error">Impossible de zipper les factures...</span><br />');

						finalCallback();
						return;
					}

					if (zipResponse.success) {
						$console.append('<span class="export-bill-finished">Zip '+currentZip+'/'+totalZip+' créé</span><br />');
						//$console.append('<span class="export-bill-finished">'+zipResponse.url+'</span><br />');

						exportResults.push(zipResponse);
					} else {
						$console.append('<span class="export-bill-error">Zip '+currentZip+'/'+totalZip+' : '+zipResponse.errorMessage+'</span><br />');
					}
					generateArchive(eventId, exportIds, exportIndex, exportResults, $console);
				});
			}
		}

		function finalCallback() {
			$buttons.removeClass('disabled');
			$('.export-bill-bloc').removeClass('processing');
		}

		function showExportResult(exportResults, $console, eventId) {
			var index = 0,
				current = null,
				$downloadLinks = $('#export-bill-bloc-'+eventId+' .export-bill-download-links').html('');

			if (exportResults.length == 0) {
				$console.append('<span class="export-bill-error">Aucun zip a télécharger</span><br />');
			} else if (exportResults.length == 1) {
				current = exportResults[0];
				$downloadLinks.append('<li><a class="export-bill-link button button-primary" href="'+current.url+'" target="_blank">  Télécharger le zip</a></li>');
			} else {
				while (index < exportResults.length) {
					current = exportResults[index++];
					$downloadLinks.append('<li><a class="export-bill-link button button-primary" href="'+current.url+'" target="_blank"> Télécharger le zip n°'+current.currentZip+'</a></li>');
				}
			}

			finalCallback();
		}
	});
}(jQuery));