import domd from 'domd'
import dispatcher from 'edispatcher'

let pageEl

export default class Navigation {
  static init() {
    pageEl = document.getElementById('page')
    const delegate = domd(document)

    delegate.on('click', '[data-load]', (ev, el) => {
      if (el.href) {
        ev.preventDefault()
        this.load(el.href)
      }
    })

    window.onpopstate = ev => {
      if (ev.srcElement.location.pathname == ev.target.location.pathname) {
        return
      }
      this.load(location.href)
    }
  }

  static parseQueryString(query) {
    const params = new URLSearchParams()
    const pairs = query.replace(/^[?;]+/, '').split(/[&;]/)
    pairs.forEach(pair => {
      const [key, value] = pair.split('=')
      params.append(decodeURIComponent(key), decodeURIComponent(value))
    })
    return params
  }

  static removeParam(url, parameter) {
  	const queryParts = url.replace(/^[?;]+/, '').split(/[&;]/)
    const filteredQuery = queryParts.filter(function(param) {
      return !param.startsWith(parameter + '=') && !param.startsWith(parameter + '[]=');
    })
    return filteredQuery.join(';')
  }

  static load(url) {
    // if (location.href !== url) {
    //   history.pushState({}, '', url)
    // }

    const opts = {
      credentials: 'same-origin',
      method: 'GET',
      headers: {
        'X-Requested-With': 'navigation'
      }
    }

    pageEl.classList.add('loading');
    fetch(url, opts).then(res => res.text()).then(body => {
      window.requestAnimationFrame(() => {
        pageEl.innerHTML = body
        pageEl.classList.remove('loading')
        const counters = JSON.parse(pageEl.querySelector('form').getAttribute('data-counters-json'))
        const author_counters = JSON.parse(pageEl.querySelector('form').getAttribute('data-author-counters-json'))
        const assignee_counters = JSON.parse(pageEl.querySelector('form').getAttribute('data-assignee-counters-json'))
        const label_counters = JSON.parse(pageEl.querySelector('form').getAttribute('data-label-counters-json'))
				const comment_range_counters = JSON.parse(pageEl.querySelector('form').getAttribute('data-comment-range-counters-json'))
        const url_path = url.replace(/https?\:\/\/[^\/]+/, '')
        history.pushState(null, '', url_path)
        dispatcher.send('page_content_loaded', {ajax: true, url: url_path}, 'navigation')
        dispatcher.send('counters_updated', counters, 'navigation')
				dispatcher.send('authors_counters_updated', author_counters, 'navigation')
				dispatcher.send('assignees_counters_updated', assignee_counters, 'navigation')
				dispatcher.send('labels_counters_updated', label_counters, 'navigation')
				dispatcher.send('comment_ranges_counters_updated', comment_range_counters, 'navigation')
      })
    })
  }
}
