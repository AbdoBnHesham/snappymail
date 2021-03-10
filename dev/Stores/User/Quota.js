import ko from 'ko';

export const QuotaUserStore = new class {
	constructor() {
		this.quota = ko.observable(0);
		this.usage = ko.observable(0);

		this.percentage = ko.computed(() => {
			const quota = this.quota(),
				usage = this.usage();

			return 0 < quota ? Math.ceil((usage / quota) * 100) : 0;
		});
	}

	/**
	 * @param {number} quota
	 * @param {number} usage
	 */
	populateData(quota, usage) {
		this.quota(quota * 1024);
		this.usage(usage * 1024);
	}
};
