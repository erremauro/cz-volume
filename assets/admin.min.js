(function ($) {
	'use strict';

	function ajaxRequest(data, onSuccess) {
		$.post(CZVolumeAdmin.ajaxUrl, data)
			.done(function (response) {
				if (response && response.success) {
					onSuccess(response);
					return;
				}
				alert((response && response.data && response.data.message) || CZVolumeAdmin.i18n.error);
			})
			.fail(function () {
				alert(CZVolumeAdmin.i18n.error);
			});
	}

	function escapeHtml(value) {
		return $('<div>').text(value || '').html();
	}

	function getChapterRows($tableBody) {
		if ($tableBody.length) {
			return $tableBody;
		}
		return $('.wp-list-table.chapters tbody');
	}

	function renderChaptersTable(chapters) {
		var $tbody = getChapterRows($('.wp-list-table.chapters tbody'));
		var items = $.isArray(chapters) ? chapters.slice(0) : [];

		if (!$tbody.length) {
			return;
		}

		items.sort(function (a, b) {
			var posA = parseInt(a.position, 10) || 0;
			var posB = parseInt(b.position, 10) || 0;
			if (posA !== posB) {
				return posA - posB;
			}
			return (parseInt(a.id, 10) || 0) - (parseInt(b.id, 10) || 0);
		});

		$tbody.empty();

		if (!items.length) {
			$tbody.append('<tr class="no-items"><td class="colspanchange" colspan="5">' + escapeHtml(CZVolumeAdmin.i18n.noChapters) + '</td></tr>');
			return;
		}

		$.each(items, function (_, chapter) {
			var postId = parseInt(chapter.post_id, 10) || 0;
			var chapterNumber = parseInt(chapter.chapter_number, 10) || 0;
			var position = parseInt(chapter.position, 10) || 0;
			var title = escapeHtml(chapter.post_title || CZVolumeAdmin.i18n.postNotFound);
			var isPrimary = parseInt(chapter.is_primary, 10) === 1;
			var editUrl = CZVolumeAdmin.editPostBaseUrl + '?post=' + postId + '&action=edit';
			var titleHtml = postId ? '<a href="' + escapeHtml(editUrl) + '">' + title + '</a>' : title;
			var primaryHtml = isPrimary
				? '<span class="cz-tag-available">' + escapeHtml(CZVolumeAdmin.i18n.yes) + '</span>'
				: '<span class="cz-tag-added">' + escapeHtml(CZVolumeAdmin.i18n.no) + '</span>';
			var removeButton = postId
				? '<button type="button" class="button button-small cz-remove-chapter" data-post-id="' + postId + '">' + escapeHtml(CZVolumeAdmin.i18n.remove) + '</button>'
				: '';

			$tbody.append(
				'<tr data-post-id="' + postId + '">' +
					'<td class="position column-position" data-colname="Posizione">' +
						'<span class="cz-drag-handle" title="' + escapeHtml(CZVolumeAdmin.i18n.dragHandle) + '">&#9776;</span> ' + position +
					'</td>' +
					'<td class="chapter_number column-chapter_number" data-colname="Capitolo">' + chapterNumber + '</td>' +
					'<td class="post_title column-post_title" data-colname="Titolo Post">' + titleHtml + '</td>' +
					'<td class="is_primary column-is_primary" data-colname="Volume Principale">' + primaryHtml + '</td>' +
					'<td class="actions column-actions" data-colname="Azioni">' + removeButton + '</td>' +
				'</tr>'
			);
		});
	}

	function renderSelectedPostLabel(postTitle) {
		var $label = $('#cz-selected-post-label');
		if (!$label.length) {
			return;
		}

		if (!postTitle) {
			$label.html('<strong>' + escapeHtml(CZVolumeAdmin.i18n.noneSelected) + '</strong>');
			return;
		}

		$label.html('<strong>' + escapeHtml(CZVolumeAdmin.i18n.selectedPost) + '</strong> ' + escapeHtml(postTitle));
	}

	$(function () {
		var $chaptersTbody = $('.wp-list-table.chapters tbody');
		var searchTimer = null;
		var $chapterNumber = $('#cz-chapter-number');
		var $positionInput = $('#cz-position');
		var positionAutoMode = true;
		var $postSearchBody = $('#cz-search-results-body');
		var $postViews = $('#cz-post-views');
		var $postPagination = $('#cz-posts-pagination');
		var $authorFilter = $('#cz-post-author-filter');
		var $availabilityFilter = $('#cz-post-availability-filter');
		var browserState = {
			status: 'all',
			authorId: 0,
			authorName: '',
			term: '',
			page: 1,
			perPage: 20,
			orderBy: 'date',
			order: 'desc',
			availability: 'all'
		};

		function renderSortHeaders() {
			var $sortableHeaders = $('#cz-post-browser .cz-sort-link');
			if (!$sortableHeaders.length) {
				return;
			}

			$sortableHeaders.each(function () {
				var $link = $(this);
				var orderBy = String($link.data('orderby') || '');
				var $th = $link.closest('th');
				var isCurrent = (orderBy === browserState.orderBy);

				if (isCurrent) {
					$th.removeClass('sortable asc desc').addClass('sorted ' + browserState.order);
					$link.attr('data-order', browserState.order === 'asc' ? 'desc' : 'asc');
					$th.attr('aria-sort', browserState.order === 'asc' ? 'ascending' : 'descending');
					return;
				}

				$th.removeClass('sorted asc').addClass('sortable desc');
				$link.attr('data-order', 'asc');
				$th.removeAttr('aria-sort');
			});
		}

		function renderPostViews(counts) {
			if (!$postViews.length) {
				return;
			}

			var allCount = (counts && counts.all) || 0;
			var publishCount = (counts && counts.publish) || 0;
			var draftCount = (counts && counts.draft) || 0;

			function viewLink(status, label, count) {
				var currentClass = browserState.status === status ? ' current' : '';
				return '<a href="#" class="cz-post-view-link' + currentClass + '" data-status="' + status + '">' +
					escapeHtml(label) + ' <span class="count">(' + count + ')</span>' +
				'</a>';
			}

			$postViews.html(
				viewLink('all', CZVolumeAdmin.i18n.all, allCount) + ' | ' +
				viewLink('publish', CZVolumeAdmin.i18n.published, publishCount) + ' | ' +
				viewLink('draft', CZVolumeAdmin.i18n.draft, draftCount)
			);
		}

		function renderAuthorFilter() {
			if (!$authorFilter.length) {
				return;
			}

			if (!browserState.authorId) {
				$authorFilter.attr('hidden', true).empty();
				return;
			}

			$authorFilter.html(
				'<span class="cz-author-filter-chip">' +
					escapeHtml(CZVolumeAdmin.i18n.author) + ': ' + escapeHtml(browserState.authorName) +
					' <button type="button" class="cz-clear-author-filter" id="cz-clear-author-filter" aria-label="' + escapeHtml(CZVolumeAdmin.i18n.clearAuthor) + '">×</button>' +
				'</span>'
			).attr('hidden', false);
		}

		function renderPostRows(items) {
			var selectedId = parseInt($('#cz-post-id').val(), 10) || 0;
			var list = $.isArray(items) ? items : [];

			if (!$postSearchBody.length) {
				return;
			}

			$postSearchBody.empty();

			if (!list.length) {
				$postSearchBody.append('<tr><td colspan="4">' + escapeHtml(CZVolumeAdmin.i18n.searchEmpty) + '</td></tr>');
				return;
			}

			$.each(list, function (_, item) {
				var postId = parseInt(item.id, 10) || 0;
				var alreadyAdded = !!item.already_added;
				var rowSelectable = alreadyAdded ? '' : ' is-selectable';
				var rowSelected = (!alreadyAdded && selectedId === postId) ? ' is-selected' : '';
				var authorName = escapeHtml(item.author_name || '—');
				var title = escapeHtml(item.title || CZVolumeAdmin.i18n.postNotFound);
				var availability = alreadyAdded
					? '<span class="cz-tag-added">' + escapeHtml(CZVolumeAdmin.i18n.alreadyAdded) + '</span>'
					: '<span class="cz-tag-available">' + escapeHtml(CZVolumeAdmin.i18n.available) + '</span>';
				var statusLabel = escapeHtml(item.status_label || item.status || '');
				var authorButton = item.author_id
					? '<button type="button" class="button-link cz-author-link" data-author-id="' + parseInt(item.author_id, 10) + '" data-author-name="' + authorName + '">' + authorName + '</button>'
					: '—';
				var hasPrimaryVolume = !!item.has_primary_volume;

				$postSearchBody.append(
					'<tr class="cz-search-row' + rowSelectable + rowSelected + '" data-post-id="' + postId + '" data-post-title="' + title + '" data-has-primary-volume="' + (hasPrimaryVolume ? '1' : '0') + '">' +
						'<td class="cz-cell-title"><strong>' + title + '</strong></td>' +
						'<td class="cz-cell-author">' + authorButton + '</td>' +
						'<td class="cz-cell-status"><span class="cz-status-pill">' + statusLabel + '</span></td>' +
						'<td class="cz-cell-availability">' + availability + '</td>' +
					'</tr>'
				);
			});
		}

		function getNextChapterNumber() {
			var maxChapter = 0;

			$('.wp-list-table.chapters tbody tr').each(function () {
				var chapterText = $.trim($(this).find('td.column-chapter_number').first().text());
				var chapterNumber = parseInt(chapterText, 10) || 0;
				if (chapterNumber > maxChapter) {
					maxChapter = chapterNumber;
				}
			});

			return Math.max(1, maxChapter + 1);
		}

		function renderPostPagination(pagination) {
			if (!$postPagination.length) {
				return;
			}

			var currentPage = (pagination && pagination.current_page) ? parseInt(pagination.current_page, 10) : 1;
			var totalPages = (pagination && pagination.total_pages) ? parseInt(pagination.total_pages, 10) : 1;

			if (totalPages <= 1) {
				$postPagination.empty();
				return;
			}

			var prevDisabled = currentPage <= 1 ? ' disabled' : '';
			var nextDisabled = currentPage >= totalPages ? ' disabled' : '';

			$postPagination.html(
				'<button type="button" class="button cz-post-page" data-page="' + (currentPage - 1) + '"' + prevDisabled + '>‹</button>' +
				'<span class="cz-page-indicator">' + escapeHtml(CZVolumeAdmin.i18n.pageLabel) + ' ' + currentPage + ' / ' + totalPages + '</span>' +
				'<button type="button" class="button cz-post-page" data-page="' + (currentPage + 1) + '"' + nextDisabled + '>›</button>'
			);
		}

		function loadAvailablePosts() {
			if (!CZVolumeAdmin.volumeId || !$postSearchBody.length) {
				return;
			}

			$postSearchBody.html('<tr><td colspan="4">' + escapeHtml(CZVolumeAdmin.i18n.loading) + '</td></tr>');

			ajaxRequest(
				{
					action: 'cz_search_posts',
					nonce: CZVolumeAdmin.nonce,
					volume_id: CZVolumeAdmin.volumeId,
					term: browserState.term,
					status: browserState.status,
					author_id: browserState.authorId,
					page: browserState.page,
					per_page: browserState.perPage,
					orderby: browserState.orderBy,
					order: browserState.order,
					availability: browserState.availability
				},
				function (response) {
					var data = response && response.data ? response.data : {};
					var filters = data.filters || {};

					if (filters.orderby) {
						browserState.orderBy = String(filters.orderby);
					}
					if (filters.order) {
						browserState.order = String(filters.order).toLowerCase() === 'asc' ? 'asc' : 'desc';
					}
					if (filters.availability) {
						browserState.availability = String(filters.availability) === 'available' ? 'available' : 'all';
					}
					if ($availabilityFilter.length) {
						$availabilityFilter.val(browserState.availability);
					}

					renderPostViews(data.views || {});
					renderSortHeaders();
					renderPostRows(data.items || []);
					renderPostPagination(data.pagination || {});
					renderAuthorFilter();
				}
			);
		}

		if ($chaptersTbody.length && CZVolumeAdmin.volumeId) {
			$chaptersTbody.sortable({
				handle: '.cz-drag-handle',
				items: 'tr',
				axis: 'y',
				update: function () {
					var positions = [];
					$chaptersTbody.find('tr').each(function () {
						var postId = $(this).data('post-id');
						if (postId) {
							positions.push(postId);
						}
					});

					ajaxRequest(
						{
							action: 'cz_update_positions',
							nonce: CZVolumeAdmin.nonce,
							volume_id: CZVolumeAdmin.volumeId,
							positions: positions
						},
						function () {
							window.location.reload();
						}
					);
				}
			});
		}

		$(document).on('click', '.cz-remove-chapter', function () {
			if (!confirm(CZVolumeAdmin.i18n.confirmRemove)) {
				return;
			}

			var postId = $(this).data('post-id');
			if (!postId || !CZVolumeAdmin.volumeId) {
				return;
			}

			ajaxRequest(
				{
					action: 'cz_remove_chapter',
					nonce: CZVolumeAdmin.nonce,
					volume_id: CZVolumeAdmin.volumeId,
					post_id: postId
				},
				function (response) {
					var chapters = response && response.data ? response.data.chapters : [];
					renderChaptersTable(chapters);
					loadAvailablePosts();

					if (parseInt($('#cz-post-id').val(), 10) === postId) {
						$('#cz-post-id').val('');
						renderSelectedPostLabel('');
					}
				}
			);
		});

		$(document).on('input', '#cz-post-search-live', function () {
			var term = $.trim($(this).val());
			if (searchTimer) {
				clearTimeout(searchTimer);
			}

			searchTimer = setTimeout(function () {
				browserState.term = term;
				browserState.page = 1;
				loadAvailablePosts();
			}, 220);
		});

		$(document).on('click', '.cz-post-view-link', function (event) {
			event.preventDefault();
			var status = $(this).data('status') || 'all';
			if (browserState.status === status) {
				return;
			}
			browserState.status = status;
			browserState.page = 1;
			loadAvailablePosts();
		});

		$(document).on('change', '#cz-post-availability-filter', function () {
			var availability = String($(this).val() || 'all');
			browserState.availability = availability === 'available' ? 'available' : 'all';
			browserState.page = 1;
			loadAvailablePosts();
		});

		$(document).on('click', '.cz-sort-link', function (event) {
			event.preventDefault();

			var orderBy = String($(this).data('orderby') || '');
			var order = String($(this).data('order') || 'asc').toLowerCase();
			if (orderBy !== 'title' && orderBy !== 'author') {
				return;
			}

			browserState.orderBy = orderBy;
			browserState.order = order === 'desc' ? 'desc' : 'asc';
			browserState.page = 1;
			loadAvailablePosts();
		});

		$(document).on('click', '.cz-author-link', function (event) {
			event.preventDefault();
			event.stopPropagation();
			var authorId = parseInt($(this).data('author-id'), 10) || 0;
			var authorName = $(this).data('author-name') || '';
			if (!authorId) {
				return;
			}

			if (browserState.authorId === authorId) {
				browserState.authorId = 0;
				browserState.authorName = '';
			} else {
				browserState.authorId = authorId;
				browserState.authorName = authorName;
			}

			browserState.page = 1;
			loadAvailablePosts();
		});

		$(document).on('click', '#cz-clear-author-filter', function (event) {
			event.preventDefault();
			browserState.authorId = 0;
			browserState.authorName = '';
			browserState.page = 1;
			loadAvailablePosts();
		});

		$(document).on('click', '.cz-post-page', function () {
			if ($(this).is(':disabled')) {
				return;
			}
			var page = parseInt($(this).data('page'), 10) || 1;
			if (page < 1 || page === browserState.page) {
				return;
			}
			browserState.page = page;
			loadAvailablePosts();
		});

		$(document).on('click', '#cz-search-results-body tr.is-selectable', function () {
			var postId = parseInt($(this).data('post-id'), 10) || 0;
			var postTitle = $(this).data('post-title') || '';
			var hasPrimaryVolume = parseInt($(this).data('has-primary-volume'), 10) === 1;
			var nextChapterNumber = getNextChapterNumber();
			if (!postId) {
				return;
			}

			$('#cz-post-id').val(postId);
			positionAutoMode = true;
			$chapterNumber.val(nextChapterNumber).trigger('input');
			$positionInput.val(nextChapterNumber);
			$('#cz-is-primary').prop('checked', !hasPrimaryVolume);
			$('#cz-search-results-body tr.is-selected').removeClass('is-selected');
			$(this).addClass('is-selected');
			renderSelectedPostLabel(postTitle);
		});

		$('#cz-add-chapter-form').on('submit', function (event) {
			event.preventDefault();

			if (!$('#cz-post-id').val()) {
				alert(CZVolumeAdmin.i18n.selectPost);
				return;
			}

			var payload = $(this).serializeArray();
			var data = {};
			$.each(payload, function (_, field) {
				data[field.name] = field.value;
			});

			ajaxRequest(data, function (response) {
				var chapters = response && response.data ? response.data.chapters : [];
				var chapterNumberInput = $('#cz-chapter-number');
				var currentChapter = parseInt(chapterNumberInput.val(), 10) || 0;

				renderChaptersTable(chapters);
				loadAvailablePosts();

				$('#cz-post-id').val('');
				renderSelectedPostLabel('');
				chapterNumberInput.val(currentChapter > 0 ? currentChapter + 1 : '');
				$('#cz-position').val(currentChapter > 0 ? currentChapter + 1 : '');
				$('#cz-is-primary').prop('checked', true);
				positionAutoMode = true;
			});
		});

		if ($chapterNumber.length && $positionInput.length) {
			$chapterNumber.on('input change', function () {
				var chapterValue = $.trim($(this).val());
				if (positionAutoMode || $.trim($positionInput.val()) === '') {
					$positionInput.val(chapterValue);
				}
			});

			$positionInput.on('input change', function () {
				var chapterValue = $.trim($chapterNumber.val());
				var positionValue = $.trim($(this).val());
				if (positionValue === '') {
					positionAutoMode = true;
					return;
				}

				positionAutoMode = (chapterValue !== '' && positionValue === chapterValue);
			});
		}

		renderSelectedPostLabel('');
		loadAvailablePosts();

		(function initVolumeTokenField() {
			var $tokenbox = $('#cz-volume-tokenbox');
			if (!$tokenbox.length) {
				return;
			}

			var $input = $('#cz-volume-token-input');
			var $chips = $('#cz-volume-chips');
			var $hidden = $('#cz-volume-hidden-inputs');
			var $suggestions = $('#cz-volume-suggestions');
			var volumes = [];

			try {
				volumes = JSON.parse($tokenbox.attr('data-volumes') || '[]');
			} catch (e) {
				volumes = [];
			}

			function selectedIds() {
				var ids = {};
				$hidden.find('.cz-volume-hidden-input').each(function () {
					ids[String($(this).val())] = true;
				});
				return ids;
			}

			function addVolume(id, title) {
				id = String(id);
				if (!id || selectedIds()[id]) {
					return;
				}
				$hidden.append('<input type="hidden" class="cz-volume-hidden-input" name="cz_post_volumes[]" value="' + escapeHtml(id) + '" />');
				$chips.append(
					'<span class="cz-volume-chip" data-volume-id="' + escapeHtml(id) + '">' +
						escapeHtml(title) +
						' <button type="button" class="cz-chip-remove" aria-label="Rimuovi">×</button>' +
					'</span>'
				);
				updateMostUsedState();
			}

			function removeVolume(id) {
				id = String(id);
				$hidden.find('.cz-volume-hidden-input[value="' + id + '"]').remove();
				$chips.find('.cz-volume-chip[data-volume-id="' + id + '"]').remove();
				updateMostUsedState();
			}

			function updateMostUsedState() {
				var ids = selectedIds();
				$('.cz-volume-most-used-link').each(function () {
					var isSelected = !!ids[String($(this).data('volume-id'))];
					$(this).toggleClass('is-selected', isSelected);
				});
			}

			function renderSuggestions(term) {
				var query = $.trim(term || '').toLowerCase();
				var ids = selectedIds();
				var items = [];

				if (query.length) {
					$.each(volumes, function (_, volume) {
						var id = String(volume.id);
						var title = (volume.title || '').toString();
						if (!title || ids[id]) {
							return;
						}
						if (title.toLowerCase().indexOf(query) !== -1) {
							items.push(volume);
						}
					});
				}

				$suggestions.empty();
				if (!items.length) {
					$suggestions.attr('hidden', true);
					return;
				}

				$.each(items.slice(0, 8), function (_, volume) {
					$suggestions.append(
						'<button type="button" class="cz-volume-suggestion" data-volume-id="' + escapeHtml(String(volume.id)) + '" data-volume-title="' + escapeHtml(volume.title) + '">' +
							escapeHtml(volume.title) +
						'</button>'
					);
				});

				$suggestions.attr('hidden', false);
			}

			$input.on('input', function () {
				renderSuggestions($(this).val());
			});

			$input.on('keydown', function (event) {
				if (event.key !== 'Enter' && event.key !== ',') {
					return;
				}
				event.preventDefault();
				var $first = $suggestions.find('.cz-volume-suggestion').first();
				if ($first.length) {
					addVolume($first.data('volume-id'), $first.data('volume-title'));
					$input.val('');
					renderSuggestions('');
				}
			});

			$(document).on('click', '.cz-volume-suggestion', function () {
				addVolume($(this).data('volume-id'), $(this).data('volume-title'));
				$input.val('').trigger('focus');
				renderSuggestions('');
			});

			$(document).on('click', '.cz-chip-remove', function () {
				removeVolume($(this).closest('.cz-volume-chip').data('volume-id'));
			});

			$(document).on('click', '.cz-volume-most-used-link', function (event) {
				event.preventDefault();
				addVolume($(this).data('volume-id'), $(this).data('volume-title'));
				$input.trigger('focus');
			});

			$(document).on('click', function (event) {
				if (!$(event.target).closest('#cz-volume-tokenbox').length) {
					$suggestions.attr('hidden', true);
				}
			});

			updateMostUsedState();
		})();

		(function initVolumeMediaFields() {
			if (!CZVolumeAdmin.volumeEditor) {
				return;
			}

			function renderPreview($targetPreview, type, attachment) {
				if (type === 'image') {
					var url = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) || attachment.url;
					$targetPreview.html('<img src="' + $('<div>').text(url).html() + '" alt="" />');
					return;
				}

				$targetPreview.html(
					'<a href="' + $('<div>').text(attachment.url).html() + '" target="_blank" rel="noopener noreferrer">' +
						$('<div>').text(attachment.filename || attachment.title || 'file').html() +
					'</a>'
				);
			}

			$(document).on('click', '.cz-media-upload', function () {
				var $button = $(this);
				var targetId = $button.data('target-id');
				var targetPreview = $button.data('target-preview');
				var type = $button.data('type');
				var libraryType = type === 'image' ? 'image' : '';

				var frame = wp.media({
					title: 'Seleziona file',
					button: { text: 'Usa questo file' },
					library: libraryType ? { type: libraryType } : {},
					multiple: false
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					var filename = (attachment.filename || '').toLowerCase();
					var mime = (attachment.mime || '').toLowerCase();

					if (type === 'epub' && !/\.epub$/.test(filename)) {
						alert('Seleziona un file EPUB (.epub).');
						return;
					}
					if (type === 'pdf' && mime !== 'application/pdf') {
						alert('Seleziona un file PDF (.pdf).');
						return;
					}

					$(targetId).val(attachment.id);
					renderPreview($(targetPreview), type, attachment);
				});

				frame.open();
			});

			$(document).on('click', '.cz-media-remove', function () {
				var $button = $(this);
				var targetId = $button.data('target-id');
				var targetPreview = $button.data('target-preview');
				var isImage = String(targetId).indexOf('cover') !== -1;

				$(targetId).val('');
				if (isImage) {
					$(targetPreview).html('<span class="cz-media-placeholder">Nessuna immagine selezionata</span>');
				} else if (String(targetId).indexOf('epub') !== -1) {
					$(targetPreview).html('<span class="cz-media-placeholder">Nessun file EPUB</span>');
				} else {
					$(targetPreview).html('<span class="cz-media-placeholder">Nessun file PDF</span>');
				}
			});
		})();
	});
})(jQuery);
