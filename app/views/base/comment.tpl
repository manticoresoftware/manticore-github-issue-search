<comment>
	<!-- <p>{>icon/comment} {body:md_preview}</p> -->
	<a class="highlight" href="https://github.com/{global.project}/issues/{parent.issue.number}#issuecomment-{id}" target="_blank" rel="noopener noreferrer">{>icon/comment} {highlight:html}</a>
	<!-- <p>{body:md_preview}</p> -->
	<p class="info">
		<img src="{user.avatar_url}" alt="{user.login}"/>
		<span>
			<a href="https://github.com/{user.login}" target="_blank" rel="noopener noreferrer">{user.login}</a> · {created_at:date}
		</span>
	</p>
	{>base/reactions}
</comment>
