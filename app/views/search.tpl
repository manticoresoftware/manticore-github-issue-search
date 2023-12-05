{>base/header}
<container class="preload">
	<p>👀 While it's loading, feel free to check out other repositories.</p>
	<ul>
		{showcase}
			<li><a href="{url}">{project}</a></li>
		{/showcase}
	</ul>
</container>
<container class="search">
	<grid>
		<cell class="filters" span="2">
			{>base/filters}
		</cell>
		<cell class="results" span="10" id="page" data-component="infinite-scroll">
			{>base/results}
		</cell>
	</grid>
</container>
{>base/footer}
