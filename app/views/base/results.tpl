<form class="header" action="{url}" method="get">
	<span class="info-wrapper">
		<b>{list.count.total}{list.count.total_more}+{/list.count.total_more} results</b> <counter>{list.time} ms</counter> in <a href="https://github.com/{repo.org}/{repo.name}" target="_blank" rel="noopener noreferrer">{repo.org}/{repo.name}</a>
	</span>
	<span class="select-wrapper">
		<label for="sort-by">Sort by:</label>
		<select id="sort-by" class="select-menu" name="sort" onchange="this.form.submit()">
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
			{issue}
				<issue>
					<p class="title">
						{is_open:} <span class="open">{>icon/open}</span>
						{is_closed:} <span class="closed">{>icon/closed}</span>
						<a href="https://github.com/{global.project}/issues/{number}" target="_blank" rel="noopener noreferrer"> {title}</a>
					</p>
					<p class="highlight">{highlight}</p>
					<!-- <p>{body:md_preview}</p> -->
					<p class="info">
						<img src="{user.avatar_url}" alt="{user.login}"/>
						<span>
							<a href="https://github.com/{user.login}" target="_blank" rel="noopener noreferrer">{user.login}</a> · {updated_at:date} · {>icon/comment} {comments} · #{number}
						</span>
					</p>
					{>base/reactions}
					{>base/labels}
				</issue>
			{/issue}
			{comment}
				<comment>
					<!-- <p>{>icon/comment} {body:md_preview}</p> -->
					<p class="highlight">{>icon/comment} {highlight}</p>
					<!-- <p>{body:md_preview}</p> -->
					<p class="info">
						<img src="{user.avatar_url}" alt="{user.login}"/>
						<span>
							<a href="https://github.com/{user.login}" target="_blank" rel="noopener noreferrer">{user.login}</a> · {created_at:date}
						</span>
					</p>
					{>base/reactions}
				</comment>
			{/comment}
		</card>
	{/items}
{/list}
{!list.items:}<p>No results found, try to search</p>
