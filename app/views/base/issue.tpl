<issue>
	<p class="title">
		{is_pull_request}
			<span class="{is_open}open{/is_open}{is_closed}closed{/is_closed}">{>icon/pull_request}</span>
		{/is_pull_request}
		{!is_pull_request}
			{is_open:} <span class="open">{>icon/open}</span>
			{is_closed:} <span class="closed">{>icon/closed}</span>
		{/!is_pull_request}
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
