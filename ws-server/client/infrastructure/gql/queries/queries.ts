export const HELLO_REQUREST = /* GraphQL */`
    query Hello {
        hello {
            currentuserid
            currentVersion
            wikiLink
        }
    }
`

export const DAILY_GEMS_RESULTS_REQUEST = /* GraphQL */`
    query dailygemsresults($day: DayFilterType!)  {
        dailygemsresults(day: $day) {
            status
            ResponseCode
            affectedRows {
                totalGems
                data {
                    userid
                    pkey
                    gems
                }
            }
        }
    }
`

export const DAILY_GEMS_STATUS_REQUEST = /* GraphQL */`
    query dailygemsstatus {
        dailygemstatus {
            status
            ResponseCode
            affectedRows {
                d0
                d1
                d2
                d3
                d4
                d5
                w0
                m0
                y0
            }
        }
    }
`