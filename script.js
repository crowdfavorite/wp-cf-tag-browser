/**
 * Tag Browser Object
 */
cftb = {};
cftb.curTags = [];
cftb.doingCatFilter = false;

/**
 * Return the HTML for the loading div
 */
cftb.tpl_loading = function() {
	return '<div class="loading"><span>' + cftbLocalized.txtLoading + '</span></div>';
};


/**
 * Does the magic for filtering the "table" of tags
 *
 * @param int this_col The current column
 * @param int next_col The next column to build
 */
cftb.direct = function(this_col, next_col) {

	// Remove all columns after (and inclusive of) next_col
	for (var i = next_col; i < 999; i++) {
		var test = jQuery('.cftb_tags [rel="column_' + i + '"]');
		test.size() ? test.remove() : i = 999;
	}
	
	// Build our selectedTags Array to be passed to PHP for filtering purposes
	selectedTags = [];
	jQuery('.cftb_tags li a.selected').each(function() {
		selectedTags[selectedTags.length] = jQuery(this).attr('rel').replace('tag-', '');
	});
	
	// Get stuff together for, and put, "loading" text in the next tag column
	loading = '<div class="column" rel="column_' + next_col + '">' + cftb.tpl_loading() + '</div>';
	this_col_elem = jQuery('.cftb_tags [rel="column_' + this_col + '"]');
	if (this_col_elem.size()) {
		this_col_elem.after(loading);
	}
	else {
		jQuery('.cftb_tags').html(loading + '<div class="clear"></div>');
	}
	
	// move the column to its right place
	jQuery('.cftb_tags [rel="column_' + next_col + '"]').css('left', (this_col * 150) + 'px');
	
	// say we're loading in the posts place
	jQuery('.cftb_posts').html(cftb.tpl_loading());
	
	// Make the actual request
	jQuery.get(
		cftbLocalized.endpoint,
		{
			cf_action: 'cftb_get_related',
			cftb_tags: selectedTags.join(','),
			cftb_cat: jQuery('#cftb_category').val(),
			randVal: Math.random()
		},
		function(result) {
			// fill in our tags
			jQuery('.cftb_tags [rel="column_' + next_col + '"]').html(result.tags);
			
			// fill in our posts
			jQuery('.cftb_posts').html(result.posts);
			
			// Attach our click/change handlers again
			cftb.handlers();

			// If we're doing our category filter and we don't have posts
			if (cftb.doingCatFilter && !result.tag_count) {
				// Set our category to All
				jQuery('#cftb_category').val('');
				
				// Turn off our cat filter
				cftb.doingCatFilter = false;
			}
			
			// Set the hash value to our tags
			cftb.setHash(selectedTags, jQuery('#cftb_category').val());
			
			// Check if have tags, or are doing the initial category filter
			if (result.tag_count && (cftb.curTags || cftb.doingCatFilter)) {
				/* We don't want to shift anything off the array if we're
				doing the category filter, we'll come back around and get 
				the curTags. */
				if (cftb.doingCatFilter) {
					cftb.doingCatFilter = false;
				}
				else {
					cftb.curTags.shift();
				}

				// If we have more tags to do then call this function again.
				if (cftb.curTags.length > 0) {
					// increment our vars
					++this_col;
					++next_col;
					
					// Mark the next tag as selected
					cftb.selectTag(this_col, cftb.curTags[0]);
					
					// Make our call again
					cftb.direct(this_col, next_col);
				}
			}
		},
		'json'
	);
};


/**
 * Hooks our change and click events to the tags and category dropdown
 */
cftb.handlers = function() {
	jQuery('#cftb_category').unbind().change(function() {
		cftb.direct(0, 1);
	});
	
	// Click Handlers for the links
	jQuery('.cftb_tags li a').unbind().click(function() {
		// Remove any other selected classes in our column
		var parent_div = jQuery(this).parent().parent().parent();
		parent_div.find('a.selected').removeClass('selected');
		
		// Add selected class to the currently clicked link
		jQuery(this).addClass('selected');
		
		// Get our current column
		this_col = parseInt(parent_div.attr('rel').replace('column_', ''), 10);
		
		// Figure out our next column
		next_col = this_col + 1;
		
		// Set our tags
		cftb.curTags = [];
		cftb.curTags.push(jQuery(this).attr('rel').replace('tag-', ''));
		
		// Make our request
		cftb.direct(this_col, next_col);
		
		// Stop default link behavior
		return false;
	});
};


/**
 * This is ran on load.  It takes any hash value and attempts 
 * to start the chain of events to parse that into the tag 
 * browsing table.
 */
cftb.parseHash = function() {
	// Utilize our hash if it's provided
	var hash = cftb.getHash();
	if (hash.length > 0) {
		// Split our hash into tags and cat ID
		var pieces = hash.split('|');
		
		// Get our tags
		cftb.curTags = pieces[0].length ? pieces[0].split(',') : [];
		
		// Get our category, and set our select box to it.
		var catID = pieces[1] || '';
		
		// If we have a category that we're filtering on, we have to do that first
		if (catID) {
			// Try setting our category value.  It's null if jQuery can't set it.
			if (jQuery('#cftb_category').val(catID).val()) {
				cftb.doingCatFilter = true;
				cftb.direct(0, 1);
			}
			else {
				// We have a non-existant category, set our category value to ''
				jQuery('#cftb_category').val('');
			}
		}
		else {
			// Select our first tag
			cftb.selectTag(1, cftb.curTags[0]);
			
			// If we couldn't select a tag, don't do anything
			if (jQuery('.cftb_tags li a.selected').size()) {
				// otherwise do the request
				cftb.direct(1, 2);
			}
		}
	}
};


/**
 * Locates the specified tag by column and tag-slug and adds the "selected" class to it
 */
cftb.selectTag = function(col, tag) {
	jQuery('.cftb_tags [rel="column_' + col + '"]').find('a[rel="tag-' + tag + '"]').addClass('selected');
};


/**
 * Sets the hash
 */
cftb.setHash = function(tags, cat) {
	// Serialize our tags
	tagStr = tags.join(',');
	
	// Only toss on a cat if we have one
	if (cat) {
		cat = '|' + cat;
	}
	window.location.hash = tags + cat;
};


/**
 * Returns the hash value, without the hash character
 */
cftb.getHash = function() {
	// substring it so we only get our tags, not the actual has mark
	return window.location.hash.substr(1);
};


jQuery(function() {
	// attach all our change and click events
	cftb.handlers();
	
	// only parse hash on page load
	cftb.parseHash();
});