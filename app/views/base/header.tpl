<header data-component="progress-bar" class="progress-bar" data-api-url="/api/repo{page_url}">
  <container>
    <grid>
      <cell class="logo" span="2">
        <a href="/">{>logo/github}</a>
        <span>Demo by <a href="https://manticoresearch.com" target="_blank" rel="noopener noreferrer">Manticore Search</a></span>
      </cell>
      <cell span="8">
      	<form action="{page_url}" method="get" data-component="search-form" data-api-url="/api/autocomplete{page_url}">
      		<grid>
      			<row>
      				<div class="input-wrapper">
      					<span class="icon">{>icon/find}</span>
      					<input class="unstyled" type="text" name="query" value="{query:html}" placeholder="Search issues & comments in {project}" autocomplete="off" autofill="off"/>
      					{form_vars:} <input type="hidden" name="{name}" value="{value}"/>
      					<button class="unstyled icon" type="submit">
      						{>icon/search}
      					</button>
      				</div>
							<div id="autocomplete" class="input-autocomplete"></div>
      			</row>
      		</grid>
      	</form>
      </cell>
      <cell span="2">
      	<a href="https://github.com/manticoresoftware/manticore-github-issue-search" target="_blank" rel="noopener noreferrer">{>icon/github} GitHub</a>
      </cell>
    </grid>
  </container>
</header>
