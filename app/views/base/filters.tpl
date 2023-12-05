<p><b>Filter by</b>{has_filters} <a href="{url}">reset</a>{/has_filters}</p>
<label>Search in</label>
<ul>
	<li{is_everywhere_active} class="active"{/is_everywhere_active}><a href="{filter_urls.everywhere}">{>icon/code} Everywhere</a><counter>{counters.total}</counter></li>
	<li{is_issues_active} class="active"{/is_issues_active}><a href="{filter_urls.issues}">{>icon/open} Issues</a><counter>{counters.issues}</counter></li>
	<li{is_pull_requests_active} class="active"{/is_pull_requests_active}><a href="{filter_urls.pull_requests}">{>icon/pull_request} Pull Requests</a><counter>{counters.pull_requests}</counter></li>
	<li{is_comments_active} class="active"{/is_comments_active}><a href="{filter_urls.comments}">{>icon/comment} Comments</a><counter>{counters.comments}</counter></li>
</ul>
<label>State</label>
<ul>
	<li{is_open_active} class="active"{/is_open_active}><a href="{filter_urls.open}"><span class="open">{>icon/open}</span> Open</a><counter>{counters.open_issues}</counter></li>
	<li{is_closed_active} class="active"{/is_closed_active}><a href="{filter_urls.closed}"><span class="closed">{>icon/closed}</span> Closed</a><counter>{counters.closed_issues}</counter></li>
</ul>
<label>Advanced</label>
<ul class="advanced">
	<li>
		<input type="checkbox" id="toggle-author" class="toggle-checkbox"/>
    <label for="toggle-author" class="toggle-label">{>icon/plus} Author</label>
		<form class="toggle-form" data-component="filterable" data-url="/{project}" data-key="authors">
			<input type="text" name="filter" placeholder="Type to filter"/>
			<ul>
				{authors}
					<li>
						<input id="author-{id}" type="checkbox" name="filters[authors][]" value="{id}"/>
						<label for="author-{id}"><img src="{avatar_url}" alt="login"/> {login}</label>
					</li>
				{/authors}
			</ul>
		</form>
	</li>
	<li>
		<input type="checkbox" id="toggle-assignee" class="toggle-checkbox"/>
    <label for="toggle-assignee" class="toggle-label">{>icon/plus} Assignee</label>
		<form class="toggle-form" data-component="filterable" data-url="/{project}" data-key="assignees">
			<input type="text" name="filter" placeholder="Type to filter"/>
			<ul>
				{assignees}
					<li>
						<input id="assignee-{id}" type="checkbox" name="filters[assignees][]" value="{id}"/>
						<label for="assignee-{id}"><img src="{avatar_url}" alt="login"/> {login}</label>
					</li>
				{/assignees}
			</ul>
		</form>
	</li>
	<li>
		<input type="checkbox" id="toggle-label" class="toggle-checkbox"/>
    <label for="toggle-label" class="toggle-label">{>icon/plus} Labels</label>
		<form class="toggle-form" data-component="filterable" data-url="/{project}" data-key="labels">
			<input type="text" name="filter" placeholder="Type to filter"/>
			<ul>
				{labels}
					<li>
						<input id="label-{id}" type="checkbox" name="filters[labels][]" value="{id}"/>
						<label for="label-{id}"><span class="color-label" style="background-color: #{color}"></span><span>{name}</span></label>
					</li>
				{/labels}
			</ul>
		</form>
	</li>
	<li>
		<input type="checkbox" id="toggle-comment-range" class="toggle-checkbox"/>
    <label for="toggle-comment-range" class="toggle-comment-range">{>icon/plus} Comments count</label>
		<form class="toggle-form" data-component="filterable" data-url="/{project}" data-key="comment_ranges">
			<ul>
				{comment_ranges}
					<li>
						<input id="comment-range-{id}" type="checkbox" name="filters[comment_range][]" value="{id}"/>
						<label for="comment-range-{id}">{name}</label>
					</li>
				{/comment_ranges}
			</ul>
		</form>
	</li>
</ul>
