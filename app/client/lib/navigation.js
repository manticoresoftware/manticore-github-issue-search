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

  static load(url) {
    if (location.href !== url) {
      history.pushState({}, '', url)
    }

    const opts = {
      credentials: 'same-origin',
      method: 'GET',
      headers: {
        'X-Requested-With': 'navigation'
      }
    }

    fetch(url, opts).then(res => res.text()).then(body => {
      window.requestAnimationFrame(() => {
        window.scrollTo(0, 0)
        pageEl.innerHTML = body
        const title_el = pageEl.querySelector('#title')
        const token_el = pageEl.querySelector('#token')
        document.title = title_el.innerText
        pageEl.removeChild(title_el)
        pageEl.removeChild(token_el)

        const url_path = url.replace(/https?\:\/\/[^\/]+/, '')
        dispatcher.send('page_content_loaded', {ajax: true, url: url_path}, 'navigation')
        dispatcher.send('token_updated', token_el.innerText, 'navigation')
      })
    })
  }
}
