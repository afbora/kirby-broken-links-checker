<template>
  <k-inside>
    <k-view class="k-broken-links-checker-view">
      <header class="k-header k-broken-links-checker-view-header">
        <h2>Broken links checker</h2>
        <k-button
            tooltip="Scan now"
            text="Scan now"
            icon="search"
            @click="crawlUrl(base)"
        />
      </header>

      <section class="k-broken-links-checker-view-section">
        <header>
          <k-headline>Checked URLs ({{ linksCount }}) | Errors found:
            ({{ errorsCount }})
          </k-headline>
        </header>

        <k-table
            :columns="{
						url: {
							label: 'Link',
							type: 'url',
							mobile: true
						},
						code: {
							label: 'Code',
							width: '1/20',
						},
						status: {
							label: 'Status',
							width: '1/20',
						},
						source: {
							label: 'Source',
							type: 'url',
							width: '5/20',
						},
						link: {
							label: 'Link content',
							type: 'html',
							width: '5/20',
						}
					}"
            :rows="urls"
            empty="No links checked yet"
        />
      </section>
    </k-view>
  </k-inside>
</template>

<script>

export default {
  props: {
    base: String,
    urls: {
      type: Array,
      default: []
    }
  },
  computed: {
    // all checked links count
    linksCount() {
      return this.urls.length;
    },
    // only check errors count
    errorsCount() {
      return this.urls.filter(u => u.status !== 200).length;
    }
  },
  methods: {
    // adds URL to list
    addToUrls(url, status, source = null, linkText = null) {
      if (this.isChecked(url) === false) {
        // normalize source url
        // no need base part
        if (source) {
          source = {
            text: source.replace(this.base, '') || '/',
            href: source
          }
        }

        // log object will be inject to list
        const log = {
          url: {
            text: url.replace(this.base, '') || '/',
            href: url
          },
          code: status,
          status: status === 200 ? "🟢" : "🔴",
          source: source,
          link: linkText,
          date: (new Date()).toLocaleString()
        };

        // add log to end of list if status OK
        // add log to top of list if status failed
        if (status === 200) {
          this.urls.push(log)
        } else {
          this.urls.unshift(log);
        }
      }
    },
    // crawl helper based on `fetch()` function
    async crawlUrl(url, source = null, linkText = null) {
      // no need check again if the url checked
      if (this.isChecked(url) === true) {
        return false;
      }

      // fetch the url
      await fetch(url)
          .then(async response => {
            // get the response
            const text = await response.text();

            // add to urls list
            this.addToUrls(url, response.status, source, linkText);

            // no need to parse the response if response is not a webpage
            if (this.isWebpage(response) !== true) {
              return false;
            }

            // parse dom
            const parser = new DOMParser();
            const htmlDocument = parser.parseFromString(text, "text/html");

            // extract all links
            const links = htmlDocument.documentElement.querySelectorAll("a");

            for (let i = 0; i < links.length; i++) {
              // get link href value
              let link = links[i].getAttribute("href");
              let linkText = links[i].innerHTML;

              // crawl the link if internal
              // external links doesn't crawl
              if (this.isInternal(link) === true) {
                await this.crawlUrl(link, url, linkText);
              }
            }
          })
          // when fetching fails
          .catch(err => {
            this.addToUrls(url, err.code, source);
          });
    },
    // checks the url is internal or external
    isInternal(url) {
      return url.startsWith(this.base);
    },
    // checks the url crawled before
    isChecked(url) {
      return this.urls.filter(u => u.url.href === url).length > 0;
    },
    // checks the url is webpage or not
    isWebpage(response) {
      return response?.headers?.get("content-type")?.startsWith("text/html");
    }
  }
};
</script>

<style>
.k-broken-links-checker-view-header {
  margin-bottom: 2rem;
  display: flex;
  justify-content: space-between;
}

.k-broken-links-checker-view .k-table thead th.k-table-column:nth-child(3),
.k-broken-links-checker-view .k-table thead th.k-table-column:nth-child(4) {
  text-align: center;
}

.k-broken-links-checker-view .k-table td:nth-child(3),
.k-broken-links-checker-view .k-table td:nth-child(4) {
  text-align: center;
}

.k-broken-links-checker-view .k-table td.k-table-empty {
  text-align: center;
}

.k-broken-links-checker-view .k-table td .k-html-field-preview {
  max-height: 250px;
  overflow-y: auto;
}

.k-broken-links-checker-view .k-table td .k-html-field-preview img {
  max-width: 100%;
}

.k-broken-links-checker-view .k-table td .k-html-field-preview,
.k-broken-links-checker-view .k-table td .k-url-field-preview a {
  white-space: unset;
}
</style>
