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
										<label for="fuzziness" title="Select the level of spell correction fuzziness">Spell correction fuzziness</label>
										<div>
											<input type="radio" id="fuzziness_0" name="fuzziness" value="0">
											<label for="fuzziness_0" title="No spell correction">0</label>
										</div>
										<div>
											<input type="radio" id="fuzziness_1" name="fuzziness" value="1">
											<label for="fuzziness_1" title="Allow 1 character difference">1</label>
										</div>
										<div>
											<input type="radio" id="fuzziness_2" name="fuzziness" value="2">
											<label for="fuzziness_2" title="Allow 2 character differences">2</label>
										</div>
										<div>
											<input type="checkbox" id="append" name="append" value="1"/>
											<label for="append" title="Check for suffixes like 'word*'">Append</label>
										</div>
										<div>
											<input type="checkbox" id="prepend" name="prepend" value="1"/>
											<label for="prepend" title="Check for prefixes like '*word'">Prepend</label>
										</div>
									</fieldset>
									<fieldset>
										<legend>Last word completion</legend>
										<label for="expansion_limit" title="Set the maximum number of characters to expand for the last word in the query">Expansion Limit</label>
										<div>
											<input type="radio" id="expansion_limit_2" name="expansion_limit" value="2">
											<label for="expansion_limit_2" title="Expand up to 2 characters">2</label>
										</div>
										<div>
											<input type="radio" id="expansion_limit_6" name="expansion_limit" value="6">
											<label for="expansion_limit_6" title="Expand up to 6 characters">6</label>
										</div>
										<div>
											<input type="radio" id="expansion_limit_12" name="expansion_limit" value="12">
											<label for="expansion_limit_12" title="Expand up to 12 characters">12</label>
										</div>
										<div>
											<input type="radio" id="expansion_limit_16" name="expansion_limit" value="16">
											<label for="expansion_limit_16" title="Expand up to 16 characters">16</label>
										</div>
										<div>
											<input type="radio" id="expansion_limit_20" name="expansion_limit" value="20">
											<label for="expansion_limit_20" title="Expand up to 20 characters">20</label>
										</div>
									</fieldset>
									<fieldset>
										<legend>Keyboard Layouts</legend>
										<div>
											<input type="checkbox" id="us" name="layouts[]" value="us"/>
											<label for="us" title="Enable English keyboard layout for spell correction">English</label>
										</div>
										<div>
											<input type="checkbox" id="ru" name="layouts[]" value="ru"/>
											<label for="ru" title="Enable Russian keyboard layout for spell correction">Russian</label>
										</div>
										<div>
											<input type="checkbox" id="ua" name="layouts[]" value="ua"/>
											<label for="ua" title="Enable Ukrainian keyboard layout for spell correction">Ukrainian</label>
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
