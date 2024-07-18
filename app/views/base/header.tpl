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
      					<button class="unstyled icon icon-configure" type="button">
      						{>icon/configure}
      					</button>
      					<button class="unstyled icon" type="submit">
      						{>icon/search}
      					</button>
      				</div>
							<div id="autocomplete" class="input-autocomplete"></div>
							<div id="configure" class="input-configure">
								<form action="" method="post">
									<fieldset>
										<legend>Query Suggestion Options</legend>
										<div>
											<input type="checkbox" id="fuzzy" name="fuzzy" value="1"/>
											<label for="fuzzy">Fuzzy</label>
										</div>
										<div>
											<input type="checkbox" id="append" name="append" value="1"/>
											<label for="append">Append</label>
										</div>
										<div>
											<input type="checkbox" id="prepend" name="prepend" value="1"/>
											<label for="prepend">Prepend</label>
										</div>
									</fieldset>
									<fieldset>
										<legend>Last word completion</legend>
										<label for="expansion_limit">Expansion Limit</label>
										<div>
											<input type="radio" id="expansion_limit_2" name="expansion_limit" value="2">
											<label for="expansion_limit_2">2</label>
										</div>
										<div>
											<input type="radio" id="expansion_limit_4" name="expansion_limit" value="4">
											<label for="expansion_limit_4">4</label>
										</div>
										<div>
											<input type="radio" id="expansion_limit_6" name="expansion_limit" value="6">
											<label for="expansion_limit_6">6</label>
										</div>
										<div>
											<input type="radio" id="expansion_limit_8" name="expansion_limit" value="8">
											<label for="expansion_limit_8">8</label>
										</div>
										<div>
											<input type="radio" id="expansion_limit_10" name="expansion_limit" value="10">
											<label for="expansion_limit_10">10</label>
										</div>
										<div>
											<input type="radio" id="expansion_limit_12" name="expansion_limit" value="12">
											<label for="expansion_limit_12">12</label>
										</div>
										<div>
											<input type="radio" id="expansion_limit_14" name="expansion_limit" value="14">
											<label for="expansion_limit_14">14</label>
										</div>
										<div>
											<input type="radio" id="expansion_limit_16" name="expansion_limit" value="16">
											<label for="expansion_limit_16">16</label>
										</div>
										<div>
											<input type="radio" id="expansion_limit_18" name="expansion_limit" value="18">
											<label for="expansion_limit_18">18</label>
										</div>
										<div>
											<input type="radio" id="expansion_limit_20" name="expansion_limit" value="20">
											<label for="expansion_limit_20">20</label>
										</div>
									</fieldset>
									<fieldset>
										<legend>Keyboard Layouts</legend>
										<div>
											<input type="checkbox" id="ru" name="layouts[]" value="ru"/>
											<label for="ru">Russian</label>
										</div>
										<div>
											<input type="checkbox" id="us" name="layouts[]" value="us"/>
											<label for="us">English</label>
										</div>
										<div>
											<input type="checkbox" id="ua" name="layouts[]" value="ua"/>
											<label for="ua">Ukrainian</label>
										</div>
									</fieldset>
								</form>

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
