{>base/header}
<container class="preload">
	<p>👀 While it's loading, feel free to check out the other repositories:</p>
	<ul>
		{showcase}
			<li><a href="/{project}">{project}</a></li>
		{/showcase}
	</ul>
	<p>🔔 To get a notification when it's ready, leave your email here:</p>
	<form action="#" method="get" class="input-wrapper" data-component="subscribe-form" data-api-url="/api/subscribe/{project}">
		<span class="icon">{>icon/email}</span>
		<input class="unstyled" type="text" name="email" placeholder="Your email"/>
		<button class="unstyled icon" type="submit">{>icon/search}</button>
	</form>
	<p style="font-size: 12px; opacity: 0.7">By entering your email, you agree to receive notifications and marketing-related emails</p>
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
