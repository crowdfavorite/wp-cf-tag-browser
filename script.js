/**
 * Tag Browser Object
 */
cftd = {};
cftd.curTags = [];
cftd.doingCatFilter = false;

/**
 * Return the HTML for the loading div
 */
cftd.tpl_loading = function() {
	return '<div class="loading"><span>' + cftb.txtLoading + '</span></div>';
};


/**
 * Does the magic for filtering the "table" of tags
 *
 * @param int this_col The current column
 * @param int next_col The next column to build
 */
cftd.direct = function(this_col, next_col) {

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
	loading = '<div class="column" rel="column_' + next_col + '">' + cftd.tpl_loading() + '</div>';
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
	jQuery('.cftb_posts').html(cftd.tpl_loading());
	
	// Make the actual request
	jQuery.get(
		cftb.endpoint,
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
			cftd.handlers();
			
			// If we're doing our category filter and we don't have posts
			if (cftd.doingCatFilter && !cftd.havePosts(result.posts)) {
				// Set our category to All
				jQuery('#cftb_category').val('');
			}
			
			// Set the hash value to our tags
			cftd.setHash(selectedTags, jQuery('#cftb_category').val());
			
			// Check if have tags, or are doing the initial category filter
			if (cftd.havePosts(result.posts) && (cftd.curTags || cftd.doingCatFilter)) {
				/* We don't want to shift anything off the array if we're
				doing the category filter, we'll come back around and get 
				the curTags. */
				if (cftd.doingCatFilter) {
					cftd.doingCatFilter = false;
				}
				else {
					cftd.curTags.shift();
				}

				// If we have more tags to do then call this function again.
				if (cftd.curTags.length > 0) {
					// increment our vars
					++this_col;
					++next_col;
					
					// Mark the next tag as selected
					cftd.selectTag(this_col, cftd.curTags[0]);
					
					// Make our call again
					cftd.direct(this_col, next_col);
				}
			}
		},
		'json'
	);
};


/**
 * Sees if we have posts in our AJAX response.
 */
cftd.havePosts = function(str) {
	return str.indexOf(cftb.txtNoPosts) == '-1';
};


/**
 * Hooks our change and click events to the tags and category dropdown
 */
cftd.handlers = function() {
	jQuery('#cftb_category').unbind().change(function() {
		cftd.direct(0, 1);
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
		cftd.curTags = [];
		cftd.curTags.push(jQuery(this).attr('rel').replace('tag-', ''));
		
		// Make our request
		cftd.direct(this_col, next_col);
		
		// Stop default link behavior
		return false;
	});
};


/**
 * This is ran on load.  It takes any hash value and attempts 
 * to start the chain of events to parse that into the tag 
 * browsing table.
 */
cftd.parseHash = function() {
	// Utilize our hash if it's provided
	var hash = cftd.getHash();
	if (hash.length > 0) {
		// Split our hash into tags and cat ID
		var pieces = hash.split('|');
		
		// Get our tags
		cftd.curTags = pieces[0].length ? pieces[0].split(',') : [];
		
		// Select our first tag
		cftd.selectTag(1, cftd.curTags[0]);

		// Get our category, and set our select box to it.
		var catID = pieces[1] || '';
		
		// If we have a category that we're filtering on, we have to do that first
		if (catID) {
			jQuery('#cftb_category').val(catID);
			cftd.doingCatFilter = true;
			cftd.direct(0, 1);
		}
		else {
			// otherwise do the request
			cftd.direct(1, 2);
		}
	}
};


/**
 * Locates the specified tag by column and tag-slug and adds the "selected" class to it
 */
cftd.selectTag = function(col, tag) {
	jQuery('.cftb_tags [rel="column_' + col + '"]').find('a[rel="tag-' + tag + '"]').addClass('selected');
};


/**
 * Sets the hash
 */
cftd.setHash = function(tags, cat) {
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
cftd.getHash = function() {
	// substring it so we only get our tags, not the actual has mark
	return window.location.hash.substr(1);
};


jQuery(function() {
	// attach all our change and click events
	cftd.handlers();
	
	// only parse hash on page load
	cftd.parseHash();
});