from flask import Flask, request, jsonify
from flask.ext.sqlalchemy import SQLAlchemy

app = Flask(__name__)
db = SQLAlchemy(app)

app.config['SQLALCHEMY_DATABASE_URI'] = 'mysql+mysqlconnector://pk:protokoll@127.0.0.1/pk'

class Entities(db.Model):
	id = db.Column(db.Integer, primary_key = True)
	org = db.Column(db.String(255))
	org_group = db.Column(db.String(255))
	cat = db.Column(db.String(255))
	domain = db.Column(db.String(255))
	domain_email = db.Column(db.String(255))
	url = db.Column(db.String(255))
	created = db.Column(db.DateTime)
	updated = db.Column(db.DateTime)


@app.route('/entities', methods=['GET'])
@app.route('/entities/<int:id>', methods=['GET'])
def entities(id=None):
	if request.method == 'GET':
		if id:
			res = Entities.query.filter_by(id=id).limit(100).offset(0).all()
		else:
			res = Entities.query.limit(100).offset(0).all()
		
		list = []
		for r in res:
			d = {
			'id': r.id,
			'org': r.org,
			'orgGroup': r.org_group,
			'cat': r.cat,
			'domain': r.domain,
			'domainEmail': r.domain_email,
			'url': r.url,
			'created': r.created,
			'updated': r.updated
			}
			list.append(d)

		return jsonify(items=list)

if __name__ == '__main__':
	app.run(debug=True, host='127.0.0.1')
