/**
 * Tag Browser Object
 */
cftd = {};


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
 * @param array doTags (optional) Array of tags to loop over.  This is primarily 
 *                     implemented for the on-load parsing of the hash tags
 */
cftd.direct = function(this_col, next_col, doTags) {

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
			
			// Set the hash value to our tags
			cftd.setHash(selectedTags.join(','));
			
			// Check if we are an object (Arrays are objects too in JS)
			if (doTags) {
				// If we have more "do_tags" then call this function again.
				doTags.shift();
				if (doTags.length > 0) {
					// increment our vars
					++this_col;
					++next_col;
					
					// Mark the next tag as selected
					cftd.selectTag(this_col, doTags[0]);
					
					// Make our call again
					cftd.direct(this_col, next_col, doTags);
				}
			}
		},
		'json'
	);
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
		var tags = hash.split(',');
		
		// Select our first tag
		cftd.selectTag(1, tags[0]);
		
		// do the request, passing our array of tags
		cftd.direct(1, 2, tags);
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
cftd.setHash = function(hashVal) {
	window.location.hash = hashVal;
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