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
