<header data-component="progress-bar" class="progress-bar" data-api-url="/api/repo/{project}">
  <container>
    <grid>
      <cell class="logo" span="2">
        <a href="/">{>logo/github}</a>
        <span>Demo by <a href="https://manticoresearch.com" target="_blank" rel="noopener noreferrer">Manticore Search</a></span>
      </cell>
      <cell span="8">
      	<form action="/{project}" method="get">
      		<grid>
      			<row>
      				<div class="input-wrapper">
      					<span class="icon">{>icon/find}</span>
      					<input class="unstyled" type="text" name="query" value="{query:html}" placeholder="Search issues & comments in {project}"/>
      					{form_vars:} <input type="hidden" name="{name}" value="{value}"/>
      					<button class="unstyled icon" type="submit">
      						{>icon/search}
      					</button>
      				</div>
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
