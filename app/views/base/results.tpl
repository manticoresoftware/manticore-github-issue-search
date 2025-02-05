<form class="header" action="{url}" method="get" data-counters-json='{counters:json}' data-author-counters-json='{author_counters:json}'
	data-assignee-counters-json='{assignee_counters:json}' data-label-counters-json='{label_counters:json}' data-comment-range-counters-json='{comment_range_counters:json}'>
<span class="info-wrapper">
	<b>{list.count.found}{list.count.found_more}+{/list.count.found_more} results</b> <counter>{list.time} ms</counter> in {!multiple_repos}<a href="/{org.name}/{repo.name}/" target="_blank" rel="noopener noreferrer">{org.name}/{repo.name}</a>{/!multiple_repos}{multiple_repos}<a href="/{org.name}/" target="_blank" rel="noopener noreferrer">{org.name}</a> repos{/multiple_repos}{show_query} for <span title="Your query had a syntax error, so we corrected it to conduct the search for you.">{search_query:html}</span>{/show_query}
</span>
	<span class="select-wrapper">
		<fieldset data-component="filterable" data-url="{page_url}" data-key="search">
			<label for="search-type">Type:</label>
			<select id="search-type" class="select-menu" name="search">
				{search_list:} <option value="{value}"{selected} selected="selected"{/selected}>{name}</option>
			</select>
		</fieldset>
		<fieldset data-component="filterable" data-url="{page_url}" data-key="sort">
			<label for="sort-by">Sort by:</label>
			<select id="sort-by" class="select-menu" name="sort">
				{sort_list:} <option value="{value}"{selected} selected="selected"{/selected}>{name}</option>
			</select>
		</fieldset>
		<input type="hidden" name="query" value="{query}"/>
		{form_vars:} {!is_sort:}<input type="hidden" name="{name}" value="{value}"/>
	</span>
</form>
{list}
	{items}
		<card>
			{comment:} <has-comment></has-comment>
			{issue:} {>base/issue}
			{comment:} {>base/comment}
		</card>
	{/items}
{/list}
{error:}<p class="error">Error: {error}</p>
{!list.items:}<p>No results found. Please try a different query or apply alternative filters.</p>
