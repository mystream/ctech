# Scope

- Production ready application that works like a simple message board
- Expose a REST interface that allows an anonymous user to submit messages and to retrieve a list of the submitted messages
- Follow sound engineering practices
- Document any trade-offs that you had to make whilst building this app

# API Location

- The API for this project is located at:
> https://ctechapi.hacking.global/

- The Messages Endpoint is located at:
> https://ctechapi.hacking.global/message/

# API Approach

- Parameters will be sent via `Headers` to avoid them being cached in Standard Request Logs

# API Usage

## Fetching a list of Messages

Endpoint
> GET https://ctechapi.hacking.global/message/

Headers
> `Messages-Matching` Phrase to limit returned messages
> `Messages-From`     Earliest date from which to return matching messages
> `Messages-Until`    Latest date up to which messages should be returned
> `Messages-Limit`    Maximum # of Results to Return

Body
`None`

Note:
If no parameters are supplied, the last 20 messages will be returned.
If an invalid value is supplied for the `From` date, we will presume the start of the current day
If an invalid value is supplied for the `Until` date, we will presume 'now'
If the `Until` date is earlier or the same as the `From` date, we will assume `Until` is 'now'
If the `Matching` parameter is invalid or too long, we will assume all matches

## Putting a Message on the Message Board

Endpoint
> PUT https://ctechapi.hacking.global/message/

Headers
`None`

Body
Contents should be supplied in the Body of the PUT Request

## Updating a Message

Endpoint
> POST https://ctechapi.hacking.global/message/

Headers
`Message-UUID-Private` The Private UUID created when the message was created
`Message-UUID-Public`  The Public UUID of the Message

Body
Contents should be supplied in the Body of the POST Request

Note:
If the Private UUID does not match an existing one, it will return a 404
If the Public UUID does not match an existing one, it will return a 404
If the Public and Private UUIDs are not matched, it will return a 404

## Deleting a Message

Endpoint
> DELETE https://ctechapi.hacking.global/message/

Headers
`Message-UUID-Private` The Private UUID created when the message was created
`Message-UUID-Public`  The Public UUID of the Message

Body
Contents should be supplied in the Body of the POST Request

Note:
If the Private UUID does not match an existing one, it will return a 404
If the Public UUID does not match an existing one, it will return a 404
If the Public and Private UUIDs are not matched, it will return a 404