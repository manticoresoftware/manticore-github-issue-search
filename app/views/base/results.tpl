<form class="header" action="{url}" method="get" data-component="filterable" data-url="/{project}" data-key="sort" data-counters-json='{counters:json}'>
	<span class="info-wrapper">
		<b>{list.count.found}{list.count.found_more}+{/list.count.found_more} results</b> <counter>{list.time} ms</counter> in <a href="https://github.com/{repo.org}/{repo.name}" target="_blank" rel="noopener noreferrer">{repo.org}/{repo.name}</a>{show_query} for <span title="Your query had a syntax error, so we corrected it to conduct the search for you.">{search_query:html}</span>{/show_query}
	</span>
	<span class="select-wrapper">
		<label for="sort-by">Sort by:</label>
		<select id="sort-by" class="select-menu" name="sort">
			{sort_list:} <option value="{value}"{selected} selected="selected"{/selected}>{name}</option>
		</select>
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
{!list.items:}<p>No results found, try to search</p>
