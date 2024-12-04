<comment>
	<!-- <p>{>icon/comment} {body:md_preview}</p> -->
	<a class="highlight" href="https://github.com/{global.org.name}/{global.repo.name}/issues/{parent.issue.number}#issuecomment-{id}" target="_blank" rel="noopener noreferrer">{>icon/comment} {highlight:highlight}</a>
	<!-- <p>{body:md_preview}</p> -->
	<p class="info">
		<img src="{user.avatar_url}" alt="{user.login}" loading="lazy"/>
		<span>
			<a href="https://github.com/{user.login}" target="_blank" rel="noopener noreferrer">{user.login}</a> · {created_at:date}
		</span>
	</p>
	{>base/reactions}
</comment>
